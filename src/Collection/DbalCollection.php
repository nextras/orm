<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Iterator;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\MySqlPlatform;
use Nextras\Dbal\Platforms\SqlServerPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\MemberAccessException;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\IRelationshipMapper;
use function count;
use function is_array;


/**
 * @template E of IEntity
 * @implements ICollection<E>
 */
class DbalCollection implements ICollection
{
	/** @var list<callable(\Traversable<E> $entities): void> */
	public array $onEntityFetch = [];

	protected IRelationshipMapper|null $relationshipMapper = null;
	protected IEntity|null $relationshipParent = null;

	/** @var Iterator<E>|null */
	protected Iterator|null $fetchIterator = null;

	/** @var array<string, Fqn> GROUP BY columns contributed by findBy() filtering. */
	private array $filterGroupByColumns = [];

	/** @var array<string, Fqn> GROUP BY columns contributed by orderBy() expressions. */
	private array $orderGroupByColumns = [];

	/** @var array<string, Fqn> Columns referenced by orderBy() expressions; required in GROUP BY once grouping is active. */
	private array $orderColumns = [];

	protected DbalQueryBuilderHelper|null $helper = null;

	/** @var list<E>|null */
	protected ?array $result = null;
	protected ?int $resultCount = null;
	protected bool $entityFetchEventTriggered = false;

	/** @var array<string, true> */
	private array $mysqlGroupByWorkaroundApplied = [];


	/**
	 * @param DbalMapper<E> $mapper
	 */
	public function __construct(
		protected readonly DbalMapper $mapper,
		protected readonly IConnection $connection,
		protected QueryBuilder $queryBuilder,
	)
	{
	}


	public function getBy(array $conds): ?IEntity
	{
		return $this->findBy($conds)->fetch();
	}


	public function getByChecked(array $conds): IEntity
	{
		return $this->findBy($conds)->fetchChecked();
	}


	public function getById($id): ?IEntity
	{
		return $this->getBy(['id' => $id]);
	}


	public function getByIdChecked($id): IEntity
	{
		$entity = $this->getById($id);
		if ($entity === null) {
			throw new NoResultException();
		}
		return $entity;
	}


	public function findBy(array $conds): ICollection
	{
		$collection = clone $this;
		$expression = $collection->getHelper()->processExpression(
			builder: $collection->queryBuilder,
			expression: $conds,
			aggregator: null,
		);
		$finalContext = $expression->havingExpression === null
			? ExpressionContext::FilterAnd
			: ExpressionContext::FilterAndWithHavingClause;
		$expression = $expression->collect($finalContext);

		$collection->applyExpressionJoins($expression);
		if ($expression->expression !== null && $expression->args !== []) {
			$collection->queryBuilder->andWhere($expression->expression, ...$expression->args);
		}
		if ($expression->havingExpression !== null && $expression->havingArgs !== []) {
			$collection->queryBuilder->andHaving($expression->havingExpression, ...$expression->havingArgs);
		}
		$collection->filterGroupByColumns = $collection->mergeColumns($collection->filterGroupByColumns, $expression->groupBy);
		$collection->rebuildGroupBy();
		return $collection;
	}


	public function orderBy($expression, string $direction = ICollection::ASC): ICollection
	{
		$collection = clone $this;
		if (is_array($expression) && !isset($expression[0])) {
			/** @var array<string, string> $expression */
			$expression = $expression; // no-op for PHPStan

			foreach ($expression as $subExpression => $subDirection) {
				$collection->addOrderByExpression($subExpression, $subDirection);
			}
		} else {
			$collection->addOrderByExpression($expression, $direction);
		}
		return $collection;
	}


	public function resetOrderBy(): ICollection
	{
		$collection = clone $this;
		$collection->orderGroupByColumns = [];
		$collection->orderColumns = [];
		$collection->queryBuilder->orderBy(null);
		$collection->rebuildGroupBy();
		return $collection;
	}


	public function limitBy(int $limit, int|null $offset = null): ICollection
	{
		$collection = clone $this;
		$collection->queryBuilder->limitBy($limit, $offset);
		return $collection;
	}


	public function fetch(): ?IEntity
	{
		if ($this->fetchIterator === null) {
			$this->fetchIterator = $this->getIterator();
		}

		if ($this->fetchIterator->valid()) {
			$current = $this->fetchIterator->current();
			$this->fetchIterator->next();
			return $current;
		}

		return null;
	}


	public function fetchChecked(): IEntity
	{
		$entity = $this->fetch();
		if ($entity === null) {
			throw new NoResultException();
		}
		return $entity;
	}


	public function fetchAll(): array
	{
		return iterator_to_array($this->getIterator(), preserve_keys: false);
	}


	public function fetchPairs(string|null $key = null, string|null $value = null): array
	{
		return FetchPairsHelper::process($this->getIterator(), $key, $value);
	}


	/**
	 * @param mixed[] $args
	 * @return never
	 * @throws MemberAccessException
	 */
	public function __call(string $name, array $args)
	{
		$class = get_class($this);
		throw new MemberAccessException("Call to undefined method $class::$name().");
	}


	/**
	 * @return Iterator<int, E>
	 */
	public function getIterator(): Iterator
	{
		if ($this->relationshipParent !== null && $this->relationshipMapper !== null) {
			/** @var Iterator<E> $entityIterator */
			$entityIterator = $this->relationshipMapper->getIterator($this->relationshipParent, $this);
		} else {
			if ($this->result === null) {
				$this->execute();
			}

			assert(is_array($this->result));
			/** @var Iterator<E> $entityIterator */
			$entityIterator = new EntityIterator($this->result);
		}

		if (!$this->entityFetchEventTriggered) {
			foreach ($this->onEntityFetch as $entityFetchCallback) {
				$entityFetchCallback($entityIterator);
			}
			$entityIterator->rewind();
			$this->entityFetchEventTriggered = true;
		}

		return $entityIterator;
	}


	public function count(): int
	{
		return iterator_count($this->getIterator());
	}


	public function countStored(): int
	{
		if ($this->relationshipParent !== null && $this->relationshipMapper !== null) {
			return $this->relationshipMapper->getIteratorCount($this->relationshipParent, $this);
		}

		return $this->getIteratorCount();
	}


	public function toMemoryCollection(): MemoryCollection
	{
		$collection = clone $this;
		$entities = $collection->fetchAll();
		return new ArrayCollection($entities, $this->mapper->getRepository());
	}


	public function setRelationshipMapper(IRelationshipMapper|null $mapper): ICollection
	{
		$this->relationshipMapper = $mapper;
		return $this;
	}


	public function getRelationshipMapper(): ?IRelationshipMapper
	{
		return $this->relationshipMapper;
	}


	public function setRelationshipParent(IEntity $parent): ICollection
	{
		$collection = clone $this;
		$collection->relationshipParent = $parent;
		return $collection;
	}


	public function subscribeOnEntityFetch(callable $callback): void
	{
		$this->onEntityFetch[] = $callback;
	}


	public function __clone()
	{
		$this->queryBuilder = clone $this->queryBuilder;
		$this->result = null;
		$this->resultCount = null;
		$this->fetchIterator = null;
		$this->entityFetchEventTriggered = false;
	}


	/**
	 * Returns the live, mutable query builder backing this collection.
	 *
	 * Callers may further mutate the returned builder, therefore any already fetched result is discarded so that the
	 * next fetch reflects the changes. Internal callers intentionally read {@see $queryBuilder} directly to avoid
	 * invalidating an in-progress iteration.
	 */
	public function getQueryBuilder(): QueryBuilder
	{
		$this->result = null;
		$this->resultCount = null;
		$this->fetchIterator = null;
		$this->entityFetchEventTriggered = false;
		return $this->queryBuilder;
	}


	protected function getIteratorCount(): int
	{
		if ($this->resultCount !== null) {
			return $this->resultCount;
		}

		$builder = clone $this->queryBuilder;

		if ($this->connection->getPlatform()->getName() === SqlServerPlatform::NAME) {
			if (!$builder->hasLimitOffsetClause()) {
				$builder->orderBy(null);
			}
		} else {
			$builder->orderBy(null);
		}

		$select = $builder->getClause('select')[0];
		if (is_array($select) && count($select) === 1 && $select[0] === "%table.*") {
			$builder->select(null);
			foreach ($this->mapper->getConventions()->getStoragePrimaryKey() as $column) {
				$builder->addSelect('%table.%column', $builder->getFromAlias(), $column);
			}
		}
		$sql = 'SELECT COUNT(*) AS count FROM (' . $builder->getQuerySql() . ') temp';
		$args = $builder->getQueryParameters();

		$this->resultCount = $this->connection->queryArgs($sql, $args)->fetchField()
			?? throw new InvalidStateException("Unable to fetch collection count.");
		return $this->resultCount;
	}


	protected function execute(): void
	{
		$result = $this->connection->queryByQueryBuilder($this->queryBuilder);

		$this->result = [];
		while (($data = $result->fetch()) !== null) {
			$entity = $this->mapper->hydrateEntity($data->toArray());
			if ($entity === null) continue;
			$this->result[] = $entity;
		}
	}


	protected function getHelper(): DbalQueryBuilderHelper
	{
		if ($this->helper === null) {
			$repository = $this->mapper->getRepository();
			$this->helper = new DbalQueryBuilderHelper($repository);
		}

		return $this->helper;
	}


	/**
	 * @param array<mixed>|string $expression
	 */
	private function addOrderByExpression(string|array $expression, string $direction): void
	{
		$expressionResult = $this->getHelper()->processExpression($this->queryBuilder, $expression, null);
		$this->applyExpressionJoins($expressionResult);

		$this->orderGroupByColumns = $this->mergeColumns($this->orderGroupByColumns, $expressionResult->groupBy);
		$this->orderColumns = $this->mergeColumns($this->orderColumns, $expressionResult->columns);
		$this->rebuildGroupBy();

		$orderByExpression = $this->getHelper()->processOrderDirection($expressionResult, $direction);
		$this->queryBuilder->addOrderBy('%ex', $orderByExpression);
	}


	/**
	 * Merges the given columns into a keyed map (deduplicated by their unescaped Fqn), preserving order.
	 *
	 * @param array<string, Fqn> $target
	 * @param list<Fqn> $columns
	 * @return array<string, Fqn>
	 */
	private function mergeColumns(array $target, array $columns): array
	{
		foreach ($columns as $fqn) {
			$target[$fqn->getUnescaped()] ??= $fqn;
		}
		return $target;
	}


	/**
	 * Rebuilds the whole GROUP BY clause from the tracked filtering and ordering columns.
	 *
	 * The clause is recomputed instead of mutated incrementally so that it does not depend on the order in which
	 * findBy()/orderBy() were called, and so that resetOrderBy() can drop the ordering-induced columns again.
	 * Order-by columns are added only when grouping is otherwise active, matching the SQL grouping requirements.
	 */
	private function rebuildGroupBy(): void
	{
		$groupBy = $this->filterGroupByColumns + $this->orderGroupByColumns;
		if (count($groupBy) > 0) {
			$groupBy += $this->orderColumns;
		}

		if (count($groupBy) === 0) {
			$this->queryBuilder->groupBy(null);
			return;
		}

		$this->queryBuilder->groupBy('%column[]', array_values($groupBy));

		if ($this->mapper->getDatabasePlatform()->getName() === MySqlPlatform::NAME) {
			$this->applyGroupByWithSameNamedColumnsWorkaround($this->queryBuilder, array_values($groupBy));
		}
	}


	private function applyExpressionJoins(DbalExpressionResult $expression): void
	{
		$mergedJoins = $this->getHelper()->mergeJoins('%and', $expression->joins);
		foreach ($mergedJoins as $join) {
			$join->applyJoin($this->queryBuilder);
		}
	}


	/**
	 * Apply workaround for MySQL that is not able to properly resolve columns when there are more same-named
	 * columns in the GROUP BY clause, even though they are properly referenced to their tables. Orm workarounds
	 * this by adding them to the SELECT clause and renames them not to conflict anywhere.
	 *
	 * @param list<Fqn> $groupBy
	 */
	private function applyGroupByWithSameNamedColumnsWorkaround(QueryBuilder $queryBuilder, array $groupBy): void
	{
		$map = [];
		foreach ($groupBy as $fqn) {
			$map[$fqn->name][$fqn->getUnescaped()] = $fqn;
		}

		foreach ($map as $fqns) {
			if (count($fqns) > 1) {
				foreach ($fqns as $key => $fqn) {
					if (isset($this->mysqlGroupByWorkaroundApplied[$key])) {
						continue;
					}

					$queryBuilder->addSelect("%column AS __nextras_fix_" . count($this->mysqlGroupByWorkaroundApplied), $fqn); // @phpstan-ignore-line
					$this->mysqlGroupByWorkaroundApplied[$key] = true;
				}
			}
		}
	}
}
