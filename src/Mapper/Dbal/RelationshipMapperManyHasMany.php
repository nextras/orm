<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Object;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Collection\IEntityIterator;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapperManyHasMany;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\LogicException;


/**
 * ManyHasMany relationship mapper for Nextras\Dbal.
 */
class RelationshipMapperManyHasMany extends Object implements IRelationshipMapperManyHasMany
{
	/** @var Connection */
	protected $connection;

	/** @var DbalMapper */
	protected $mapperOne;

	/** @var DbalMapper */
	protected $mapperTwo;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IEntityIterator[] */
	protected $cacheEntityIterator;

	/** @var int[] */
	protected $cacheCounts;

	/** @var string */
	protected $joinTable;

	/** @var string */
	protected $primaryKeyFrom;

	/** @var string */
	protected $primaryKeyTo;

	/** @var IRepository */
	protected $targetRepository;


	public function __construct(Connection $connection, DbalMapper $mapperOne, DbalMapper $mapperTwo, PropertyMetadata $metadata)
	{
		$this->connection = $connection;
		$this->mapperOne = $mapperOne;
		$this->mapperTwo = $mapperTwo;
		$this->metadata = $metadata;

		$parameters = $mapperOne->getManyHasManyParameters($metadata, $mapperTwo);
		$this->joinTable = $parameters[0];

		if ($this->metadata->relationship->isMain) {
			$this->targetRepository = $this->mapperTwo->getRepository();
			list($this->primaryKeyFrom, $this->primaryKeyTo) = $parameters[1];
		} else {
			$this->targetRepository = $this->mapperOne->getRepository();
			list($this->primaryKeyTo, $this->primaryKeyFrom) = $parameters[1];
		}
	}


	// ==== ITERATOR ===================================================================================================


	public function getIterator(IEntity $parent, ICollection $collection)
	{
		/** @var IEntityIterator $iterator */
		$iterator = $this->execute($collection, $parent);
		$iterator->setDataIndex($parent->id);
		return $iterator;
	}


	protected function execute(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$cacheKey = $this->calculateCacheKey($builder, $preloadIterator, $parent);

		$data = & $this->cacheEntityIterator[$cacheKey];
		if ($data !== NULL) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->id];
		$data = $this->fetchByTwoPassStrategy($builder, $values);
		return $data;
	}


	private function fetchByTwoPassStrategy(QueryBuilder $builder, array $values)
	{
		$sourceTable = $builder->getFromAlias();
		$targetTable = QueryBuilderHelper::getAlias($this->joinTable);

		$builder = clone $builder;
		$builder->leftJoin($sourceTable, $this->joinTable, $targetTable, "$targetTable.{$this->primaryKeyTo} = {$sourceTable}." . $this->targetRepository->getMapper()->getStorageReflection()->getStoragePrimaryKey()[0]);
		$builder->addSelect("$targetTable.$this->primaryKeyTo");
		$builder->addSelect("$targetTable.$this->primaryKeyFrom");

		if ($builder->hasLimitOffsetClause()) { // todo !== 1
			$sqls = $args = [];
			foreach ($values as $value) {
				$builderPart = clone $builder;
				$builderPart->andWhere('%column = %any', "$targetTable.$this->primaryKeyFrom", $value);
				$sqls[] = $builderPart->getQuerySQL();
				$args = array_merge($args, $builderPart->getQueryParameters());
			}

			$query = '(' . implode(') UNION ALL (', $sqls) . ')';
			$result = $this->connection->queryArgs($query, $args);

		} else {
			$builder->andWhere('%column IN %any', "$targetTable.$this->primaryKeyFrom", $values);
			$result = $this->connection->queryArgs($builder->getQuerySQL(), $builder->getQueryParameters());
		}

		$values = [];
		foreach ($result as $row) {
			$values[$row->{$this->primaryKeyTo}] = NULL;
		}

		if (count($values) === 0) {
			return new EntityIterator([]);
		}

		$entitiesResult = $this->targetRepository->findBy(['id' => array_keys($values)]);
		$entities = $entitiesResult->fetchPairs('id', NULL);

		$grouped = [];
		foreach ($result as $row) {
			$grouped[$row->{$this->primaryKeyFrom}][] = $entities[$row->{$this->primaryKeyTo}];
		}

		return new EntityIterator($grouped);
	}


	// ==== ITERATOR COUNT =============================================================================================


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		$counts = $this->executeCounts($collection, $parent);
		return isset($counts[$parent->id]) ? $counts[$parent->id] : 0;
	}


	protected function executeCounts(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$cacheKey = $this->calculateCacheKey($builder, $preloadIterator, $parent);

		$data = & $this->cacheCounts[$cacheKey];
		if ($data !== NULL) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->id];
		$data = $this->fetchCounts($builder, $values);
		return $data;
	}


	private function fetchCounts(QueryBuilder $builder, array $values)
	{
		$sourceTable = $builder->getFromAlias();
		$targetTable = QueryBuilderHelper::getAlias($this->joinTable);

		$builder = clone $builder;
		$builder->leftJoin($sourceTable, $this->joinTable, $targetTable, "$targetTable.{$this->primaryKeyTo} = {$sourceTable}." . $this->targetRepository->getMapper()->getStorageReflection()->getStoragePrimaryKey()[0]);
		$builder->addSelect("$targetTable.$this->primaryKeyFrom");
		$builder->orderBy(NULL);

		if ($builder->hasLimitOffsetClause()) {
			$sqls = [];
			$args = [];
			foreach ($values as $value) {
				$build = clone $builder;
				$build->andWhere("%column = %any", $this->primaryKeyFrom, $value);

				$sqls[] = "SELECT $value as {$this->primaryKeyFrom}, COUNT(*) AS count FROM (" . $build->getQuerySql() . ') temp';
				$args = array_merge($args, $build->getQueryParameters());
			}

			$sql = '(' . implode(') UNION ALL (', $sqls) . ')';
			$result = $this->connection->queryArgs($sql, $args);

		} else {
			$builder->andWhere("%column IN %any", $this->primaryKeyFrom, $values);
			$builder->addSelect("COUNT(%column) as count", $this->primaryKeyTo);
			$builder->groupBy('%column', $this->primaryKeyFrom);
			$result = $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());
		}

		$counts = [];
		foreach ($result as $row) {
			$counts[$row->{$this->primaryKeyFrom}] = $row->count;
		}
		return $counts;
	}


	// ==== OTHERS =====================================================================================================


	public function add(IEntity $parent, array $add)
	{
		$this->mapperOne->beginTransaction();
		$list = $this->buildList($parent, $add);
		$this->connection->query('INSERT INTO %table %values[]', $this->joinTable, $list);
	}


	public function remove(IEntity $parent, array $remove)
	{
		$this->mapperOne->beginTransaction();
		$list = $this->buildList($parent, $remove);
		$this->connection->query(
			'DELETE FROM %table WHERE %column[] IN %any',
			$this->joinTable,
			array_keys(reset($list)),
			array_map('array_values', $list)
		);
	}


	protected function buildList(IEntity $parent, array $entries)
	{
		if (!$this->metadata->relationship->isMain) {
			throw new LogicException('ManyHasMany relationship has to be persited in the primary mapper.');
		}

		$list = [];
		$primaryId = $parent->id;
		foreach ($entries as $id) {
			$list[] = [
				$this->primaryKeyFrom => $primaryId,
				$this->primaryKeyTo => $id,
			];
		}

		return $list;
	}


	protected function calculateCacheKey(QueryBuilder $builder, $preloadIterator, $parent)
	{
		return md5($builder->getQuerySql() . json_encode($builder->getQueryParameters())
			. ($preloadIterator ? spl_object_hash($preloadIterator) : json_encode($parent->id)));
	}

}
