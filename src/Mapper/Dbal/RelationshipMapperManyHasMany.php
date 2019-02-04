<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Iterator;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\IRelationshipMapperManyHasMany;


class RelationshipMapperManyHasMany implements IRelationshipMapperManyHasMany
{
	/** @var IConnection */
	protected $connection;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var string */
	protected $joinTable;

	/** @var string */
	protected $primaryKeyFrom;

	/** @var string */
	protected $primaryKeyTo;

	/** @var DbalMapper */
	protected $targetMapper;

	/** @var MultiEntityIterator[] */
	protected $cacheEntityIterators;

	/** @var int[] */
	protected $cacheCounts;

	/** @var DbalMapperCoordinator */
	private $mapperCoordinator;


	public function __construct(IConnection $connection, DbalMapper $mapperOne, DbalMapper $mapperTwo, DbalMapperCoordinator $mapperCoordinator, PropertyMetadata $metadata)
	{
		assert($metadata->relationship !== null);
		$this->connection = $connection;
		$this->metadata = $metadata;

		if ($metadata->relationship->isMain) {
			$parameters = $mapperOne->getManyHasManyParameters($metadata, $mapperTwo);
		} else {
			assert($metadata->relationship->property !== null);
			$parameters = $mapperOne->getManyHasManyParameters(
				$metadata->relationship->entityMetadata->getProperty($metadata->relationship->property),
				$mapperTwo
			);
		}
		$this->joinTable = $parameters[0];

		if ($metadata->relationship->isMain) {
			$this->targetMapper = $mapperTwo;
			[$this->primaryKeyFrom, $this->primaryKeyTo] = $parameters[1];
		} else {
			$this->targetMapper = $mapperOne;
			[$this->primaryKeyTo, $this->primaryKeyFrom] = $parameters[1];
		}
		$this->mapperCoordinator = $mapperCoordinator;
	}


	public function clearCache()
	{
		$this->cacheEntityIterators = [];
		$this->cacheCounts = [];
	}


	// ==== ITERATOR ===================================================================================================


	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		assert($collection instanceof DbalCollection);
		$iterator = clone $this->execute($collection, $parent);
		$iterator->setDataIndex($parent->getValue('id'));
		return $iterator;
	}


	protected function execute(DbalCollection $collection, IEntity $parent): MultiEntityIterator
	{
		$preloadIterator = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->getValue('id')];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		/** @var MultiEntityIterator|null $data */
		$data = & $this->cacheEntityIterators[$cacheKey];

		if ($data !== null) {
			return $data;
		}

		$data = $this->fetchByTwoPassStrategy($builder, $values);
		return $data;
	}


	private function fetchByTwoPassStrategy(QueryBuilder $builder, array $values): MultiEntityIterator
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
			"{$sourceTable}." . $this->targetMapper->getStorageReflection()->getStoragePrimaryKey()[0]
		);
		$builder->select('%column', "$targetTable.$this->primaryKeyTo");
		$builder->addSelect('%column', "$targetTable.$this->primaryKeyFrom");

		if ($builder->hasLimitOffsetClause()) {
			$result = $this->processMultiResult($builder, $values, $targetTable);

		} else {
			$builder->andWhere('%column IN %any', "$targetTable.$this->primaryKeyFrom", $values);
			$result = $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());
		}

		$values = [];
		foreach ($result as $row) {
			$values[$row->{$this->primaryKeyTo}] = null;
		}

		if (count($values) === 0) {
			return new MultiEntityIterator([]);
		}

		$entitiesResult = $this->targetMapper->findAll()->findBy(['id' => array_keys($values)]);
		$entities = $entitiesResult->fetchPairs('id', null);

		$grouped = [];
		foreach ($result as $row) {
			$grouped[$row->{$this->primaryKeyFrom}][] = $entities[$row->{$this->primaryKeyTo}];
		}

		return new MultiEntityIterator($grouped);
	}


	// ==== ITERATOR COUNT =============================================================================================


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		assert($collection instanceof DbalCollection);
		$counts = $this->executeCounts($collection, $parent);
		$id = $parent->getValue('id');
		return $counts[$id] ?? 0;
	}


	protected function executeCounts(DbalCollection $collection, IEntity $parent)
	{
		$preloadIterator = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->getValue('id')];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		/** @var int|null $data */
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
			"{$sourceTable}." . $this->targetMapper->getStorageReflection()->getStoragePrimaryKey()[0]
		);
		$builder->select('%column', "$targetTable.$this->primaryKeyFrom");

		if ($builder->hasLimitOffsetClause()) {
			$result = $this->processMultiCountResult($builder, $values);

		} else {
			$builder->addSelect('COUNT(%column) as count', "$targetTable.$this->primaryKeyTo");
			$builder->orderBy(null);
			$builder->andWhere('%column IN %any', "$targetTable.$this->primaryKeyFrom", $values);
			$builder->groupBy('%column', "$targetTable.$this->primaryKeyFrom");
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

		$this->mapperCoordinator->beginTransaction();
		$list = $this->buildList($parent, $add);
		$this->connection->query('INSERT INTO %table %values[]', $this->joinTable, $list);
	}


	public function remove(IEntity $parent, array $remove)
	{
		if (!$remove) {
			return;
		}

		$this->mapperCoordinator->beginTransaction();
		$list = $this->buildList($parent, $remove);
		$this->connection->query(
			'DELETE FROM %table WHERE %multiOr',
			$this->joinTable,
			$list
		);
	}


	protected function buildList(IEntity $parent, array $entries): array
	{
		assert($this->metadata->relationship !== null);
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


	protected function processMultiResult(QueryBuilder $builder, array $values, string $targetTable)
	{
		if ($this->connection->getPlatform()->getName() === 'mssql') {
			$result = [];
			foreach ($values as $value) {
				$builderPart = clone $builder;
				$builderPart->andWhere('%column = %any', "$targetTable.$this->primaryKeyFrom", $value);
				$result = array_merge($this->connection->queryByQueryBuilder($builderPart)->fetchAll(), $result);
			}
			return $result;

		} else {
			$sqls = $args = [];
			foreach ($values as $value) {
				$builderPart = clone $builder;
				$builderPart->andWhere('%column = %any', "$targetTable.$this->primaryKeyFrom", $value);
				$sqls[] = $builderPart->getQuerySql();
				$args = array_merge($args, $builderPart->getQueryParameters());
			}

			$query = '(' . implode(') UNION ALL (', $sqls) . ')';
			return $this->connection->queryArgs($query, $args);
		}
	}


	protected function processMultiCountResult(QueryBuilder $builder, array $values)
	{
		if ($this->connection->getPlatform()->getName() === 'mssql') {
			$result = [];
			foreach ($values as $value) {
				$builderPart = clone $builder;
				$builderPart->andWhere("%column = %any", $this->primaryKeyFrom, $value);
				$result = array_merge($this->connection->queryArgs(
					"SELECT %any AS %column, COUNT(*) AS [count] FROM (" . $builderPart->getQuerySql() . ') [temp]',
					array_merge([$value, $this->primaryKeyFrom], $builderPart->getQueryParameters())
				)->fetchAll(), $result);
			}
			return $result;

		} else {
			$sqls = [];
			$args = [];
			$builder->orderBy(null);
			foreach ($values as $value) {
				$builderPart = clone $builder;
				$builderPart->andWhere("%column = %any", $this->primaryKeyFrom, $value);
				$sqls[] = "SELECT %any AS %column, COUNT(*) AS [count] FROM (" . $builderPart->getQuerySql() . ') [temp]';
				$args[] = $value;
				$args[] = $this->primaryKeyFrom;
				$args = array_merge($args, $builderPart->getQueryParameters());
			}

			$sql = '(' . implode(') UNION ALL (', $sqls) . ')';
			$result = $this->connection->queryArgs($sql, $args);
			return $result;
		}
	}


	protected function calculateCacheKey(QueryBuilder $builder, array $values): string
	{
		return md5($builder->getQuerySql() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
