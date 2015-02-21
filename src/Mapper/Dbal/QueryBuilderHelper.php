<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Object;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\StorageReflection\IDbStorageReflection;
use Nextras\Orm\InvalidArgumentException;
use Traversable;


/**
 * QueryBuilderHelper for Nextras\Dbal.
 */
class QueryBuilderHelper extends Object
{
	/** @var IModel */
	private $model;

	/** @var IMapper */
	private $mapper;

	/** @var MetadataStorage */
	private $metadataStorage;


	public function __construct(IModel $model, IMapper $mapper)
	{
		$this->model = $model;
		$this->mapper = $mapper;
		$this->metadataStorage = $model->getMetadataStorage();
	}


	/**
	 * Transforms orm condition to sql expression for Nette Database.
	 *
	 * @param  string       $expression
	 * @param  mixed        $value
	 * @param  QueryBuilder $builder
	 * @return string
	 */
	public function parseJoinExpressionWithOperator($expression, $value, QueryBuilder $builder)
	{
		list($chain, $operator) = ConditionParserHelper::parseCondition($expression);

		if ($operator === ConditionParserHelper::OPERATOR_EQUAL) {
			if (is_array($value) || $value instanceof Traversable) {
				$operator = ' IN';
			} elseif ($value === NULL) {
				$operator = ' IS';
			} else {
				$operator = ' =';
			}
		} elseif ($operator === ConditionParserHelper::OPERATOR_NOT_EQUAL) {
			if (is_array($value) || $value instanceof Traversable) {
				$operator = ' NOT IN';
			} elseif ($value === NULL) {
				$operator = ' IS NOT';
			} else {
				$operator = ' !=';
			}
		} else {
			$operator = " $operator";
		}

		return $this->normalizeAndAddJoins($chain, $this->mapper, $builder) . $operator;
	}


	public function parseJoinExpression($expression, QueryBuilder $builder)
	{
		list($levels) = ConditionParserHelper::parseCondition($expression);
		return $this->normalizeAndAddJoins($levels, $this->mapper, $builder);
	}


	private function normalizeAndAddJoins(array $levels, IMapper $sourceMapper, QueryBuilder $builder)
	{
		/** @var IDbStorageReflection $sourceReflection */

		$column = array_pop($levels);
		$entityMeta = $this->metadataStorage->get($sourceMapper->getRepository()->getEntityClassNames()[0]);

		$sourceAlias = $builder->getFromAlias();
		$sourceReflection = $sourceMapper->getStorageReflection();

		foreach ($levels as $level) {
			$property = $entityMeta->getProperty($level);
			if (!$property->relationshipRepository) {
				throw new InvalidArgumentException("Entity {$entityMeta->className}::\${$level} does not contain a relationship.");
			}

			$targetMapper     = $this->model->getRepository($property->relationshipRepository)->getMapper();
			$targetReflection = $targetMapper->getStorageReflection();

			if ($property->relationshipType === $property::RELATIONSHIP_ONE_HAS_MANY) {
				$targetColumn = $targetReflection->convertEntityToStorageKey($property->relationshipProperty);
				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];

			} elseif ($property->relationshipType === $property::RELATIONSHIP_MANY_HAS_MANY) {
				if ($property->relationshipIsMain) {
					list($joinTable, list($inColumn, $outColumn)) = $sourceMapper->getManyHasManyParameters($targetMapper);
				} else {
					list($joinTable, list($outColumn, $inColumn)) = $targetMapper->getManyHasManyParameters($sourceMapper);
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

			} else {
				$targetColumn = $targetReflection->getStoragePrimaryKey()[0];
				$sourceColumn = $sourceReflection->convertEntityToStorageKey($level);
			}

			$targetTable = $targetMapper->getTableName();
			$targetAlias = self::getAlias($targetTable);

			$builder->leftJoin(
				$sourceAlias,
				$targetTable,
				$targetAlias,
				"[$sourceAlias.$sourceColumn] = [$targetAlias.$targetColumn]"
			);

			$sourceAlias = $targetAlias;
			$sourceMapper = $targetMapper;
			$sourceReflection = $targetReflection;
			$entityMeta = $this->metadataStorage->get($sourceMapper->getRepository()->getEntityClassNames()[0]);
		}


		$entityMeta->getProperty($column); // check if property exists
		$column = $sourceReflection->convertEntityToStorageKey($column);
		return "{$sourceAlias}.{$column}";
	}


	public static function getAlias($name)
	{
		static $counter = 1;
		if (preg_match('#^([a-z0-9_]+\.){0,2}+([a-z0-9_]+?)$#i', $name, $m)) {
			return $m[2];
		}

		return '_join' . $counter++;
	}

}
