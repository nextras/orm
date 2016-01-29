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
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Collection\IEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\IRelationshipMapperManyHasMany;
use Nextras\Orm\Repository\IRepository;


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
		$iterator = clone $this->execute($collection, $parent);
		$iterator->setDataIndex($parent->getValue('id'));
		return $iterator;
	}


	protected function execute(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->getValue('id')];
		$cacheKey = $this->calculateCacheKey($builder, $values);

		$data = & $this->cacheEntityIterator[$cacheKey];
		if ($data !== null) {
			return $data;
		}

		$data = $this->fetchByTwoPassStrategy($builder, $values);
		return $data;
	}


	private function fetchByTwoPassStrategy(QueryBuilder $builder, array $values)
	{
		$sourceTable = $builder->getFromAlias();
		$targetTable = QueryBuilderHelper::getAlias($this->joinTable);

		$builder = clone $builder;
		$builder->leftJoin(
			$sourceTable,
			'%table',
			$targetTable,
			'%column = %column',
			// args
			$this->joinTable,
			"$targetTable.{$this->primaryKeyTo}",
			"{$sourceTable}." . $this->targetRepository->getMapper()->getStorageReflection()->getStoragePrimaryKey()[0]
		);
		$builder->addSelect('%column', "$targetTable.$this->primaryKeyTo");
		$builder->addSelect('%column', "$targetTable.$this->primaryKeyFrom");

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
			$values[$row->{$this->primaryKeyTo}] = null;
		}

		if (count($values) === 0) {
			return new EntityIterator([]);
		}

		$entitiesResult = $this->targetRepository->findBy(['id' => array_keys($values)]);
		$entities = $entitiesResult->fetchPairs('id', null);

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
		$id = $parent->getValue('id');
		return isset($counts[$id]) ? $counts[$id] : 0;
	}


	protected function executeCounts(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->getValue('id')];
		$cacheKey = $this->calculateCacheKey($builder, $values);

		$data = & $this->cacheCounts[$cacheKey];
		if ($data !== null) {
			return $data;
		}

		$data = $this->fetchCounts($builder, $values);
		return $data;
	}


	private function fetchCounts(QueryBuilder $builder, array $values)
	{
		$sourceTable = $builder->getFromAlias();
		$targetTable = QueryBuilderHelper::getAlias($this->joinTable);

		$builder = clone $builder;
		$builder->leftJoin(
			$sourceTable,
			'%table',
			$targetTable,
			'%column = %column',
			// args
			$this->joinTable,
			"$targetTable.{$this->primaryKeyTo}",
			"{$sourceTable}." . $this->targetRepository->getMapper()->getStorageReflection()->getStoragePrimaryKey()[0]
		);
		$builder->addSelect('%column', "$targetTable.$this->primaryKeyFrom");
		$builder->orderBy(null);

		if ($builder->hasLimitOffsetClause()) {
			$sqls = [];
			$args = [];
			foreach ($values as $value) {
				$build = clone $builder;
				$build->andWhere("%column = %any", $this->primaryKeyFrom, $value);
				$sqls[] = "SELECT %any AS %column, COUNT(*) AS [count] FROM (" . $build->getQuerySql() . ') [temp]';
				$args[] = $value;
				$args[] = $this->primaryKeyFrom;
				$args = array_merge($args, $build->getQueryParameters());
			}

			$sql = '(' . implode(') UNION ALL (', $sqls) . ')';
			$result = $this->connection->queryArgs($sql, $args);

		} else {
			$builder->addSelect('COUNT(%column) as count', $this->primaryKeyTo);
			$builder->andWhere('%column IN %any', $this->primaryKeyFrom, $values);
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
		if (!$add) {
			return;
		}

		$this->mapperOne->beginTransaction();
		$list = $this->buildList($parent, $add);
		$this->connection->query('INSERT INTO %table %values[]', $this->joinTable, $list);
	}


	public function remove(IEntity $parent, array $remove)
	{
		if (!$remove) {
			return;
		}

		$this->mapperOne->beginTransaction();
		$list = $this->buildList($parent, $remove);
		$this->connection->query(
			'DELETE FROM %table WHERE (%column[]) IN %any',
			$this->joinTable,
			array_keys(reset($list)),
			array_map('array_values', $list)
		);
	}


	protected function buildList(IEntity $parent, array $entries)
	{
		if (!$this->metadata->relationship->isMain) {
			throw new LogicException('ManyHasMany relationship has to be persisted in the primary mapper.');
		}

		$list = [];
		$primaryId = $parent->getValue('id');
		foreach ($entries as $id) {
			$list[] = [
				$this->primaryKeyFrom => $primaryId,
				$this->primaryKeyTo => $id,
			];
		}

		return $list;
	}


	protected function calculateCacheKey(QueryBuilder $builder, array $values)
	{
		return md5($builder->getQuerySql() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
