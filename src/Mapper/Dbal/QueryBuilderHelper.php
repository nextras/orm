<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Mapper\Dbal\StorageReflection\IStorageReflection;
use Nextras\Orm\Model\IModel;
use Traversable;


/**
 * QueryBuilderHelper for Nextras\Dbal.
 */
class QueryBuilderHelper
{
	/** @var IModel */
	private $model;

	/** @var DbalMapper */
	private $mapper;


	public function __construct(IModel $model, DbalMapper $mapper)
	{
		$this->model = $model;
		$this->mapper = $mapper;
	}


	public static function getAlias(string $name): string
	{
		static $counter = 1;
		if (preg_match('#^([a-z0-9_]+\.){0,2}+([a-z0-9_]+?)$#i', $name, $m)) {
			return $m[2];
		}

		return '_join' . $counter++;
	}


	public function processCallExpr(QueryBuilder $builder, array $expr): array
	{
		if (isset($expr[0])) {
			$operator = array_shift($expr);
			return $this->mapper->processFunctionCall($builder, $operator, $expr);

		} else {
			return $this->mapper->processFunctionCall($builder, ICollection::AND, $expr);
		}
	}


	public function processPropertyExpr(QueryBuilder $builder, string $propertyExpr): string
	{
		list($chain, $sourceEntity) = ConditionParserHelper::parsePropertyExpr($propertyExpr);
		list($storageReflection, $alias, $entityMetadata, $propertyName) = $this->normalizeAndAddJoins($chain, $sourceEntity, $builder);
		assert($storageReflection instanceof IStorageReflection);
		assert($entityMetadata instanceof EntityMetadata);

		return $this->toColumnExpr($entityMetadata, $propertyName, $storageReflection, $alias);
	}


	public function processPropertyWithValueExpr(QueryBuilder $builder, string $propertyExpr, $value): array
	{
		list($chain, $sourceEntity) = ConditionParserHelper::parsePropertyExpr($propertyExpr);
		list($storageReflection, $alias, $entityMetadata, $propertyName) = $this->normalizeAndAddJoins($chain, $sourceEntity, $builder);
		assert($storageReflection instanceof IStorageReflection);
		assert($entityMetadata instanceof EntityMetadata);

		$columnExpr = $this->toColumnExpr($entityMetadata, $propertyName, $storageReflection, $alias);

		if ($value instanceof Traversable) {
			$value = iterator_to_array($value);

		} elseif ($value instanceof IEntity) {
			$value = $value->getValue('id');
		}

		$tmp = $storageReflection->convertEntityToStorage([$propertyName => $value]);
		$convertedValue = reset($tmp);

		$targetProperty = $entityMetadata->getProperty($propertyName);
		if ($targetProperty->isPrimary && $targetProperty->isVirtual && count($entityMetadata->getPrimaryKey()) > 1) {
			if (!isset($convertedValue[0][0])) {
				$convertedValue = [$convertedValue];
			}
		}

		return [$columnExpr, $convertedValue];
	}


	/**
	 * @return array [IStorageReflection $sourceReflection, string $sourceAlias, EntityMetadata $sourceEntityMeta, string $propertyName]
	 */
	private function normalizeAndAddJoins(array $levels, $sourceEntity, QueryBuilder $builder): array
	{
		$propertyName = array_pop($levels);
		$sourceMapper = $this->mapper;
		$sourceAlias = $builder->getFromAlias();
		$sourceReflection = $sourceMapper->getStorageReflection();
		$sourceEntityMeta = $sourceMapper->getRepository()->getEntityMetadata($sourceEntity);

		foreach ($levels as $levelIndex => $level) {
			$property = $sourceEntityMeta->getProperty($level);
			if ($property->relationship === null) {
				throw new InvalidArgumentException("Entity {$sourceEntityMeta->className}::\${$level} does not contain a relationship.");
			}

			$targetMapper = $this->model->getRepository($property->relationship->repository)->getMapper();
			assert($targetMapper instanceof DbalMapper);
			$targetReflection = $targetMapper->getStorageReflection();
			$targetEntityMetadata = $property->relationship->entityMetadata;

			$relType = $property->relationship->type;
			if ($relType === Relationship::ONE_HAS_MANY) {
				$targetColumn = $targetReflection->convertEntityToStorageKey($property->relationship->property);
				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];
				$this->makeDistinct($builder);

			} elseif ($relType === Relationship::ONE_HAS_ONE && !$property->relationship->isMain) {
				$targetColumn = $targetReflection->convertEntityToStorageKey($property->relationship->property);
				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];

			} elseif ($relType === Relationship::MANY_HAS_MANY) {
				$targetColumn = $targetReflection->getStoragePrimaryKey()[0];
				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];
				$this->makeDistinct($builder);

				if ($property->relationship->isMain) {
					list($joinTable, list($inColumn, $outColumn)) = $sourceMapper->getManyHasManyParameters($property, $targetMapper);
				} else {
					$sourceProperty = $targetEntityMetadata->getProperty($property->relationship->property);
					list($joinTable, list($outColumn, $inColumn)) = $targetMapper->getManyHasManyParameters($sourceProperty, $sourceMapper);
				}

				$builder->leftJoin(
					$sourceAlias,
					$joinTable,
					self::getAlias($joinTable),
					"[$sourceAlias.$sourceColumn] = [$joinTable.$inColumn]"
				);

				$sourceAlias = $joinTable;
				$sourceColumn = $outColumn;

			} else {
				$targetColumn = $targetReflection->getStoragePrimaryKey()[0];
				$sourceColumn = $sourceReflection->convertEntityToStorageKey($level);
			}

			$targetTable = $targetMapper->getTableName();
			$targetAlias = $level . str_repeat('_', $levelIndex);

			$builder->leftJoin(
				$sourceAlias,
				$targetTable,
				$targetAlias,
				"[$sourceAlias.$sourceColumn] = [$targetAlias.$targetColumn]"
			);

			$sourceAlias = $targetAlias;
			$sourceMapper = $targetMapper;
			$sourceReflection = $targetReflection;
			$sourceEntityMeta = $targetEntityMetadata;
		}

		return [$sourceReflection, $sourceAlias, $sourceEntityMeta, $propertyName];
	}


	private function makeDistinct(QueryBuilder $builder)
	{
		$baseTable = $builder->getFromAlias();
		$primaryKey = $this->mapper->getStorageReflection()->getStoragePrimaryKey();

		$groupBy = [];
		foreach ($primaryKey as $column) {
			$groupBy[] = "[{$baseTable}.{$column}]";
		}

		$builder->groupBy(...$groupBy);
	}


	private function toColumnExpr(EntityMetadata $entityMetadata, string $propertyName, IStorageReflection $storageReflection, string $alias): string
	{
		$propertyMetadata = $entityMetadata->getProperty($propertyName);
		if ($propertyMetadata->isPrimary && $propertyMetadata->isVirtual) { // primary-proxy
			$primaryKey = $entityMetadata->getPrimaryKey();
			if (count($primaryKey) > 1) { // composite primary key
				$pair = [];
				foreach ($primaryKey as $columnName) {
					$columnName = $storageReflection->convertEntityToStorageKey($columnName);
					$pair[] = "[{$alias}.{$columnName}]";
				}
				return '(' . implode(', ', $pair) . ')';

			} else {
				$propertyName = $primaryKey[0];
			}
		}

		$columnName = $storageReflection->convertEntityToStorageKey($propertyName);
		$columnExpr = "[{$alias}.{$columnName}]";

		return $columnExpr;
	}
}
