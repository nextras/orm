<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Object;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\Dbal\StorageReflection\IStorageReflection;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Traversable;


/**
 * QueryBuilderHelper for Nextras\Dbal.
 */
class QueryBuilderHelper extends Object
{
	/** @var IModel */
	private $model;

	/** @var DbalMapper */
	private $mapper;

	/** @var MetadataStorage */
	private $metadataStorage;


	public function __construct(IModel $model, DbalMapper $mapper)
	{
		$this->model = $model;
		$this->mapper = $mapper;
		$this->metadataStorage = $model->getMetadataStorage();
	}


	public function processWhereExpressions(array $conditions, QueryBuilder $builder, bool & $distinctNeeded = null)
	{
		$whereArg = $this->processRecursivelyWhereExpressions($conditions, $builder, $distinctNeeded);
		$builder->andWhere('%ex', $whereArg);
	}


	/**
	 * Transforms orm order by expression and adds it to QueryBuilder.
	 */
	public function processOrderByExpression(string $expression, string $direction, QueryBuilder $builder)
	{
		list($chain,, $sourceEntity) = ConditionParserHelper::parseCondition($expression);
		list($reflection, $alias, $entityMetadata, $column) = $this->normalizeAndAddJoins($chain, $sourceEntity, $builder, $distinctNeeded);
		assert($reflection instanceof IStorageReflection);
		if ($distinctNeeded) {
			throw new LogicException("Cannot order by '$expression' expression, includes has many relationship.");
		}

		$entityMetadata->getProperty($column);
		$column = $reflection->convertEntityToStorageKey($column);
		$builder->addOrderBy("[$alias.$column]" . ($direction === ICollection::DESC ? ' DESC' : ''));
	}


	private function processRecursivelyWhereExpressions(array $conditions, QueryBuilder $builder, bool & $distinctNeeded = null): array
	{
		$whereArgs = ['', []];
		if (!isset($conditions[0])) {
			$whereArgs[0] = '%and';
		} else {
			$whereArgs[0] = $conditions[0] === ICollection::AND ? '%and' : '%or';
			$conditions = $conditions[1];
		}

		foreach ($conditions as $expression => $value) {
			if (is_int($expression)) {
				$whereArgs[1][] = $this->processRecursivelyWhereExpressions($value, $builder, $distinctNeeded);
			} else {
				$whereArgs[1][] = $this->processWhereExpression($expression, $value, $builder, $distinctNeeded);
			}
		}

		if (count($whereArgs[1]) === 1) {
			return $whereArgs[1][0];
		} else {
			return $whereArgs;
		}
	}


	private function processWhereExpression(string $expression, $value, QueryBuilder $builder, bool & $distinctNeeded = null): array
	{
		list($chain, $operator, $sourceEntity) = ConditionParserHelper::parseCondition($expression);

		if ($value instanceof Traversable) {
			$value = iterator_to_array($value);
		} elseif ($value instanceof IEntity) {
			$value = $value->getValue('id');
		}

		if (is_array($value) && count($value) === 0) {
			return [$operator === ConditionParserHelper::OPERATOR_EQUAL ? '1=0' : '1=1'];
		}


		list($storageReflection, $alias, $entityMetadata, $column) = $this->normalizeAndAddJoins($chain, $sourceEntity, $builder, $distinctNeeded);
		assert($storageReflection instanceof IStorageReflection);
		assert($entityMetadata instanceof EntityMetadata);


		$targetProperty = $entityMetadata->getProperty($column);
		if ($targetProperty->isPrimary && $targetProperty->isVirtual) { // primary-proxy
			$primaryKey = $entityMetadata->getPrimaryKey();
			if (count($primaryKey) > 1) { // composite primary key
				list($expression, $modifier, $value) = $this->processMultiColumn($storageReflection, $primaryKey, $value, $alias);
			} else {
				$column = reset($primaryKey);
				list($expression, $modifier, $value) = $this->processColumn($storageReflection, $column, $value, $alias);
			}
		} else {
			list($expression, $modifier, $value) = $this->processColumn($storageReflection, $column, $value, $alias);
		}

		$operator = $this->getSqlOperator($value, $operator);
		return ["$expression $operator $modifier", $value];
	}


	/**
	 * @return array [IStorageReflection $sourceRefleciton, string $sourceAlias, EntityMetadata $sourceEntityMeta, string $column]
	 */
	private function normalizeAndAddJoins(array $levels, $sourceEntity, QueryBuilder $builder, & $distinctNeeded = false)
	{
		$column = array_pop($levels);
		$sourceMapper = $this->mapper;
		$sourceAlias = $builder->getFromAlias();
		$sourceReflection = $sourceMapper->getStorageReflection();
		$sourceEntityMeta = $this->metadataStorage->get($sourceEntity ?: $sourceMapper->getRepository()->getEntityClassNames()[0]);

		foreach ($levels as $levelIndex => $level) {
			$property = $sourceEntityMeta->getProperty($level);
			if ($property->relationship === null) {
				throw new InvalidArgumentException("Entity {$sourceEntityMeta->className}::\${$level} does not contain a relationship.");
			}

			$targetMapper = $this->model->getRepository($property->relationship->repository)->getMapper();
			$targetReflection = $targetMapper->getStorageReflection();
			$targetEntityMetadata = $this->metadataStorage->get($property->relationship->entity);

			$relType = $property->relationship->type;
			if ($relType === Relationship::ONE_HAS_MANY || ($relType === Relationship::ONE_HAS_ONE && !$property->relationship->isMain)) {
				$targetColumn = $targetReflection->convertEntityToStorageKey($property->relationship->property);
				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];
				$distinctNeeded = $relType === Relationship::ONE_HAS_MANY;

			} elseif ($relType === Relationship::MANY_HAS_MANY) {
				if ($property->relationship->isMain) {
					list($joinTable, list($inColumn, $outColumn)) = $sourceMapper->getManyHasManyParameters($property, $targetMapper);
				} else {
					$sourceProperty = $targetEntityMetadata->getProperty($property->relationship->property);
					list($joinTable, list($outColumn, $inColumn)) = $targetMapper->getManyHasManyParameters($sourceProperty, $sourceMapper);
				}

				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];

				$builder->leftJoin(
					$sourceAlias,
					$joinTable,
					self::getAlias($joinTable),
					"[$sourceAlias.$sourceColumn] = [$joinTable.$inColumn]"
				);

				$sourceAlias = $joinTable;
				$sourceColumn = $outColumn;
				$targetColumn = $targetReflection->getStoragePrimaryKey()[0];
				$distinctNeeded = true;

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

		return [$sourceReflection, $sourceAlias, $sourceEntityMeta, $column];
	}


	public static function getAlias(string $name): string
	{
		static $counter = 1;
		if (preg_match('#^([a-z0-9_]+\.){0,2}+([a-z0-9_]+?)$#i', $name, $m)) {
			return $m[2];
		}

		return '_join' . $counter++;
	}


	protected function getSqlOperator($value, string $operator): string
	{
		if ($operator === ConditionParserHelper::OPERATOR_EQUAL) {
			if (is_array($value)) {
				return 'IN';
			} elseif ($value === null) {
				return 'IS';
			} else {
				return '=';
			}

		} elseif ($operator === ConditionParserHelper::OPERATOR_NOT_EQUAL) {
			if (is_array($value)) {
				return 'NOT IN';
			} elseif ($value === null) {
				return 'IS NOT';
			} else {
				return '!=';
			}

		} else {
			return $operator;
		}
	}


	private function processColumn(IStorageReflection $sourceReflection, string $column, $value, string $sourceAlias): array
	{
		$converted = $sourceReflection->convertEntityToStorage([$column => $value]);
		$column = key($converted);

		if (($pos = strpos($column, '%')) !== false) {
			$modifier = substr($column, $pos);
			$column = substr($column, 0, $pos);
		} else {
			$modifier = '%any';
		}

		$value = current($converted);
		return [
			"[{$sourceAlias}.{$column}]",
			$modifier,
			$value,
		];
	}


	private function processMultiColumn(IStorageReflection $sourceReflection, array $primaryKey, $value, string $sourceAlias): array
	{
		$pair = [];
		foreach ($primaryKey as $column) {
			$column = $sourceReflection->convertEntityToStorageKey($column);
			$pair[] = "[{$sourceAlias}.{$column}]";
		}
		if (!isset($value[0][0])) {
			$value = [$value];
		}
		return [
			'(' . implode(', ', $pair) . ')',
			'%any',
			$value,
		];
	}
}
