<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Utils\Arrays;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\CustomFunctions\IQueryBuilderFilterFunction;
use Nextras\Orm\Mapper\Dbal\CustomFunctions\IQueryBuilderFunction;
use Nextras\Orm\Mapper\Dbal\Helpers\ColumnReference;
use Nextras\Orm\Mapper\Dbal\StorageReflection\IStorageReflection;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;


/**
 * QueryBuilder helper for Nextras\Dbal.
 */
class QueryBuilderHelper
{
	/** @var IModel */
	private $model;

	/** @var IRepository */
	private $repository;

	/** @var DbalMapper */
	private $mapper;


	public static function getAlias(string $name): string
	{
		static $counter = 1;
		if (preg_match('#^([a-z0-9_]+\.){0,2}+([a-z0-9_]+?)$#i', $name, $m)) {
			return $m[2];
		}

		return '_join' . $counter++;
	}


	public function __construct(IModel $model, IRepository $repository, DbalMapper $mapper)
	{
		$this->model = $model;
		$this->repository = $repository;
		$this->mapper = $mapper;
	}


	public function processApplyFunction(QueryBuilder $builder, string $function, array $expr): QueryBuilder
	{
		$customFunction = $this->repository->getCollectionFunction($function);
		if (!$customFunction instanceof IQueryBuilderFunction) {
			throw new InvalidStateException("Custom function $function has to implement IQueryBuilderFunction interface.");
		}

		return $customFunction->processQueryBuilderFilter($this, $builder, $expr);
	}


	public function processFilterFunction(QueryBuilder $builder, array $expr): array
	{
		$function = isset($expr[0]) ? array_shift($expr) : ICollection::AND;
		$customFunction = $this->repository->getCollectionFunction($function);
		if (!$customFunction instanceof IQueryBuilderFilterFunction) {
			throw new InvalidStateException("Custom function $function has to implement IQueryBuilderFilterFunction interface.");
		}

		return $customFunction->processQueryBuilderFilter($this, $builder, $expr);
	}


	public function processPropertyExpr(QueryBuilder $builder, string $propertyExpr): ColumnReference
	{
		[$chain, $sourceEntity] = ConditionParserHelper::parsePropertyExpr($propertyExpr);
		$propertyName = array_pop($chain);
		[$storageReflection, $alias, $entityMetadata] = $this->normalizeAndAddJoins($chain, $sourceEntity, $builder);
		assert($storageReflection instanceof IStorageReflection);
		assert($entityMetadata instanceof EntityMetadata);
		$propertyMetadata = $entityMetadata->getProperty($propertyName);
		$column = $this->toColumnExpr($entityMetadata, $propertyMetadata, $storageReflection, $alias);
		return new ColumnReference($column, $propertyMetadata, $entityMetadata, $storageReflection);
	}


	public function normalizeValue($value, ColumnReference $columnReference)
	{
		if (isset($columnReference->propertyMetadata->types['array'])) {
			if (is_array($value) && !is_array(reset($value))) {
				$value = [$value];
			}
			if ($columnReference->propertyMetadata->isPrimary) {
				foreach ($value as $subValue) {
					if (!Arrays::isList($subValue)) {
						throw new InvalidArgumentException('Composite primary value has to be passed as a list, without array keys.');
					}
				}
			}
		}

		if ($columnReference->propertyMetadata->container) {
			$property = $columnReference->propertyMetadata->getPropertyPrototype();
			if (is_array($value)) {
				$value = array_map(function ($subValue) use ($property) {
					return $property->convertToRawValue($subValue);
				}, $value);
			} else {
				$value = $property->convertToRawValue($value);
			}
		}

		$tmp = $columnReference->storageReflection->convertEntityToStorage([$columnReference->propertyMetadata->name => $value]);
		$value = reset($tmp);

		return $value;
	}


	/**
	 * @return array [IStorageReflection $sourceReflection, string $sourceAlias, EntityMetadata $sourceEntityMeta]
	 */
	private function normalizeAndAddJoins(array $levels, $sourceEntity, QueryBuilder $builder): array
	{
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
				assert($property->relationship->property !== null);
				$targetColumn = $targetReflection->convertEntityToStorageKey($property->relationship->property);
				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];
				$this->makeDistinct($builder);
			} elseif ($relType === Relationship::ONE_HAS_ONE && !$property->relationship->isMain) {
				assert($property->relationship->property !== null);
				$targetColumn = $targetReflection->convertEntityToStorageKey($property->relationship->property);
				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];
			} elseif ($relType === Relationship::MANY_HAS_MANY) {
				$targetColumn = $targetReflection->getStoragePrimaryKey()[0];
				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];
				$this->makeDistinct($builder);

				if ($property->relationship->isMain) {
					assert($sourceMapper instanceof DbalMapper);
					[$joinTable, [$inColumn, $outColumn]] = $sourceMapper->getManyHasManyParameters($property, $targetMapper);
				} else {
					assert($sourceMapper instanceof DbalMapper);
					assert($property->relationship->property !== null);
					$sourceProperty = $targetEntityMetadata->getProperty($property->relationship->property);
					[$joinTable, [$outColumn, $inColumn]] = $targetMapper->getManyHasManyParameters($sourceProperty, $sourceMapper);
				}

				$builder->leftJoin($sourceAlias, "[$joinTable]", self::getAlias($joinTable), "[$sourceAlias.$sourceColumn] = [$joinTable.$inColumn]");

				$sourceAlias = $joinTable;
				$sourceColumn = $outColumn;
			} else {
				$targetColumn = $targetReflection->getStoragePrimaryKey()[0];
				$sourceColumn = $sourceReflection->convertEntityToStorageKey($level);
			}

			$targetTable = $targetMapper->getTableName();
			$targetAlias = implode('_', array_slice($levels, 0, $levelIndex + 1));

			$builder->leftJoin($sourceAlias, "[$targetTable]", $targetAlias, "[$sourceAlias.$sourceColumn] = [$targetAlias.$targetColumn]");

			$sourceAlias = $targetAlias;
			$sourceMapper = $targetMapper;
			$sourceReflection = $targetReflection;
			$sourceEntityMeta = $targetEntityMetadata;
		}

		return [$sourceReflection, $sourceAlias, $sourceEntityMeta];
	}


	/**
	 * @return string|array
	 */
	private function toColumnExpr(EntityMetadata $entityMetadata, PropertyMetadata $propertyMetadata, IStorageReflection $storageReflection, string $alias)
	{
		if ($propertyMetadata->isPrimary && $propertyMetadata->isVirtual) { // primary-proxy
			$primaryKey = $entityMetadata->getPrimaryKey();
			if (count($primaryKey) > 1) { // composite primary key
				$pair = [];
				foreach ($primaryKey as $columnName) {
					$columnName = $storageReflection->convertEntityToStorageKey($columnName);
					$pair[] = "{$alias}.{$columnName}";
				}
				return $pair;
			} else {
				$propertyName = $primaryKey[0];
			}
		} else {
			$propertyName = $propertyMetadata->name;
		}

		$columnName = $storageReflection->convertEntityToStorageKey($propertyName);
		$columnExpr = "{$alias}.{$columnName}";
		return $columnExpr;
	}


	private function makeDistinct(QueryBuilder $builder)
	{
		$baseTable = $builder->getFromAlias();
		if ($this->mapper->getDatabasePlatform()->getName() === 'mssql') {
			$builder->select('DISTINCT %table.*', $baseTable);

		} else {
			$primaryKey = $this->mapper->getStorageReflection()->getStoragePrimaryKey();

			$groupBy = [];
			foreach ($primaryKey as $column) {
				$groupBy[] = "{$baseTable}.{$column}";
			}

			$builder->groupBy('%column[]', $groupBy);
		}
	}
}
