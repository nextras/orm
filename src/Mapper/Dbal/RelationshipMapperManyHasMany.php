<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


use Iterator;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Row;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\LogicException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Mapper\IRelationshipMapperManyHasMany;
use function array_keys;
use function array_merge;
use function assert;
use function count;
use function implode;
use function json_encode;
use function ksort;
use function md5;


class RelationshipMapperManyHasMany implements IRelationshipMapperManyHasMany
{
	protected string|Fqn $joinTable;
	protected string $primaryKeyFrom;
	protected string $primaryKeyTo;

	/** @var array<string, MultiEntityIterator> */
	protected array $cacheEntityIterators;

	/** @var array<string, array<int>> */
	protected array $cacheCounts;


	/**
	 * @param DbalMapper<IEntity> $targetMapper
	 * @param DbalMapper<IEntity> $sourceMapper
	 */
	public function __construct(
		protected readonly IConnection $connection,
		protected readonly DbalMapper $targetMapper,
		DbalMapper $sourceMapper,
		protected readonly DbalMapperCoordinator $mapperCoordinator,
		protected readonly PropertyMetadata $metadata,
	)
	{
		assert($metadata->relationship !== null);

		if ($metadata->relationship->isMain) {
			$parameters = $sourceMapper->getManyHasManyParameters($metadata, $targetMapper);
			$this->joinTable = $parameters[0];
			[$this->primaryKeyFrom, $this->primaryKeyTo] = $parameters[1];
		} else {
			assert($metadata->relationship->property !== null);
			$parameters = $targetMapper->getManyHasManyParameters(
				$metadata->relationship->entityMetadata->getProperty($metadata->relationship->property),
				$sourceMapper,
			);
			$this->joinTable = $parameters[0];
			[$this->primaryKeyTo, $this->primaryKeyFrom] = $parameters[1];
		}
	}


	public function clearCache(): void
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


	/**
	 * @param DbalCollection<IEntity> $collection
	 */
	protected function execute(DbalCollection $collection, IEntity $parent): MultiEntityIterator
	{
		$preloadIterator = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadIterator !== null ? $preloadIterator->getPreloadValues('id') : [$parent->getValue('id')];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		$data = &$this->cacheEntityIterators[$cacheKey];

		if ($data !== null) {
			return $data;
		}

		$data = $this->fetchByTwoPassStrategy($builder, $values);
		return $data;
	}


	/**
	 * @param list<mixed> $values
	 */
	private function fetchByTwoPassStrategy(QueryBuilder $builder, array $values): MultiEntityIterator
	{
		$sourceTable = $builder->getFromAlias();
		/** @var literal-string $targetTable */
		$targetTable = DbalQueryBuilderHelper::getAlias($this->joinTable);

		$hasGroupBy = $builder->getClause('group')[0] !== null;
		$hasOrderBy = $builder->getClause('order')[0] !== null;

		$builder = clone $builder;
		$builder->joinLeft(
			"%table AS %table",
			'%column = %column',
			// args
			$this->joinTable,
			$targetTable,
			"$targetTable.{$this->primaryKeyTo}",
			"{$sourceTable}." . $this->targetMapper->getConventions()->getStoragePrimaryKey()[0],
		);

		$builder->select('%column', "$targetTable.$this->primaryKeyTo");
		$builder->addSelect('%column', "$targetTable.$this->primaryKeyFrom");

		if ($hasGroupBy) {
			$builder->addGroupBy('%column', "$targetTable.$this->primaryKeyTo");
			$builder->addGroupBy('%column', "$targetTable.$this->primaryKeyFrom");
		}

		if ($builder->hasLimitOffsetClause()) {
			if ($hasGroupBy && $hasOrderBy) {
				throw new NotSupportedException(
					"Relationship cannot be fetched as it combines has-many joins, ORDER BY and LIMIT clause.",
				);
			}
			$result = $this->processMultiResult($builder, $values, $targetTable);

		} else {
			$builder->andWhere('%column IN %any', "$targetTable.$this->primaryKeyFrom", $values);
			if ($hasGroupBy && $hasOrderBy) {
				/** @var literal-string $sql */
				$sql = $builder->getQuerySql();
				$builder = $this->connection->createQueryBuilder()
					->select('DISTINCT *')
					->from("($sql)", '__tmp', ...$builder->getQueryParameters());
			}
			$result = $this->connection->queryByQueryBuilder($builder);
		}

		$values = [];
		foreach ($result as $groupingRow) {
			$values[$groupingRow->{$this->primaryKeyTo}] = null;
		}

		if (count($values) === 0) {
			return new MultiEntityIterator([]);
		}

		ksort($values); // make ids sorted deterministically
		$entitiesResult = $this->targetMapper->findAll()->findBy(['id' => array_keys($values)]);
		$entities = $entitiesResult->fetchPairs('id', null);

		$grouped = [];
		foreach ($result as $row) {
			$grouped[$row->{$this->primaryKeyFrom}][] = $entities[$row->{$this->primaryKeyTo}];
		}

		return new MultiEntityIterator($grouped);
	}


	// ==== ITERATOR COUNT =============================================================================================


	/**
	 * @param ICollection<IEntity> $collection
	 */
	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		assert($collection instanceof DbalCollection);
		$counts = $this->executeCounts($collection, $parent);
		$id = $parent->getValue('id');
		return $counts[$id] ?? 0;
	}


	/**
	 * @param DbalCollection<IEntity> $collection
	 * @return array<int|string, int>
	 */
	protected function executeCounts(DbalCollection $collection, IEntity $parent): array
	{
		$preloadIterator = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadIterator !== null ? $preloadIterator->getPreloadValues('id') : [$parent->getValue('id')];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		$data = &$this->cacheCounts[$cacheKey];

		if ($data !== null) {
			return $data;
		}

		/** @noinspection PhpUnnecessaryLocalVariableInspection */
		$data = $this->fetchCounts($builder, $values);
		return $data;
	}


	/**
	 * @param list<mixed> $values
	 * @return array<int|string, int>
	 */
	private function fetchCounts(QueryBuilder $builder, array $values): array
	{
		$sourceTable = $builder->getFromAlias();
		/** @var literal-string $targetTable */
		$targetTable = DbalQueryBuilderHelper::getAlias($this->joinTable);

		$builder = clone $builder;
		$builder->joinLeft(
			'%table AS %table',
			'%column = %column',
			// args
			$this->joinTable,
			$targetTable,
			"$targetTable.{$this->primaryKeyTo}",
			"{$sourceTable}." . $this->targetMapper->getConventions()->getStoragePrimaryKey()[0],
		);
		$builder->select('%column', "$targetTable.$this->primaryKeyFrom");

		if ($builder->hasLimitOffsetClause()) {
			$result = $this->processMultiCountResult($builder, $values);

		} else {
			$builder->addSelect('COUNT(DISTINCT %column) AS [count]', "$targetTable.$this->primaryKeyTo");
			$builder->orderBy(null);
			$builder->andWhere('%column IN %any', "$targetTable.$this->primaryKeyFrom", $values);
			$builder->addGroupBy('%column', "$targetTable.$this->primaryKeyFrom");
			$result = $this->connection->queryByQueryBuilder($builder);
		}

		$counts = [];
		foreach ($result as $row) {
			$counts[$row->{$this->primaryKeyFrom}] = $row->count;
		}
		return $counts;
	}


	// ==== OTHERS =====================================================================================================

	public function add(IEntity $parent, array $addIds): void
	{
		if (count($addIds) === 0) {
			return;
		}

		$this->mapperCoordinator->beginTransaction();
		$list = $this->buildList($parent, $addIds);
		$this->connection->query('INSERT INTO %table %values[]', $this->joinTable, $list);
	}


	public function remove(IEntity $parent, array $removeIds): void
	{
		if (count($removeIds) === 0) {
			return;
		}

		$this->mapperCoordinator->beginTransaction();
		$list = $this->buildList($parent, $removeIds);
		$this->connection->query(
			'DELETE FROM %table WHERE %multiOr',
			$this->joinTable,
			$list,
		);
	}


	/**
	 * @param list<mixed> $entries
	 * @return list<array<string, mixed>>
	 */
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


	/**
	 * @param list<mixed> $values
	 * @return iterable<Row>
	 */
	protected function processMultiResult(QueryBuilder $builder, array $values, string $targetTable): iterable
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


	/**
	 * @param list<mixed> $values
	 * @return iterable<Row>
	 */
	protected function processMultiCountResult(QueryBuilder $builder, array $values): iterable
	{
		if ($this->connection->getPlatform()->getName() === 'mssql') {
			$result = [];
			foreach ($values as $value) {
				$builderPart = clone $builder;
				$builderPart->andWhere("%column = %any", $this->primaryKeyFrom, $value);
				$result = array_merge($this->connection->queryArgs(
					"SELECT %any AS %column, COUNT(*) AS [count] FROM (" . $builderPart->getQuerySql() . ') [temp]',
					array_merge([$value, $this->primaryKeyFrom], $builderPart->getQueryParameters()),
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


	/**
	 * @param list<mixed> $values
	 */
	protected function calculateCacheKey(QueryBuilder $builder, array $values): string
	{
		return md5($builder->getQuerySql() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
