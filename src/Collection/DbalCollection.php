<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Iterator;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\MemberAccessException;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\IRelationshipMapper;
use function count;
use function is_array;
use function str_repeat;


/**
 * @template E of IEntity
 * @implements ICollection<E>
 */
class DbalCollection implements ICollection
{
	/** @var list<callable(\Traversable<E> $entities): void> */
	public $onEntityFetch = [];

	/** @var IRelationshipMapper|null */
	protected $relationshipMapper;

	/** @var IEntity|null */
	protected $relationshipParent;

	/** @var Iterator<E>|null */
	protected $fetchIterator;

	/** @var DbalMapper<E> */
	protected $mapper;

	/** @var IConnection */
	protected $connection;

	/** @var QueryBuilder */
	protected $queryBuilder;

	/** @var array<mixed> FindBy expressions for deferred processing */
	protected $filtering = [];

	/** @var array<array{DbalExpressionResult, string}> OrderBy expression result & sorting direction */
	protected $ordering = [];

	/** @var DbalQueryBuilderHelper */
	protected $helper;

	/** @var list<E>|null */
	protected $result;

	/** @var int|null */
	protected $resultCount;

	/** @var bool */
	protected $entityFetchEventTriggered = false;


	/**
	 * @param DbalMapper<E> $mapper
	 */
	public function __construct(DbalMapper $mapper, IConnection $connection, QueryBuilder $queryBuilder)
	{
		$this->mapper = $mapper;
		$this->connection = $connection;
		$this->queryBuilder = $queryBuilder;
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
		$collection->filtering[] = $conds;
		return $collection;
	}


	public function orderBy($expression, string $direction = ICollection::ASC): ICollection
	{
		$collection = clone $this;
		$helper = $collection->getHelper();
		if (is_array($expression) && !isset($expression[0])) {
			/** @var array<string, string> $expression */
			$expression = $expression; // no-op for PHPStan

			foreach ($expression as $subExpression => $subDirection) {
				$collection->ordering[] = [
					$helper->processExpression($collection->queryBuilder, $subExpression, ExpressionContext::FilterAnd, null),
					$subDirection,
				];
			}
		} else {
			$collection->ordering[] = [
				$helper->processExpression($collection->queryBuilder, $expression, ExpressionContext::ValueExpression, null),
				$direction,
			];
		}
		return $collection;
	}


	public function resetOrderBy(): ICollection
	{
		$collection = clone $this;
		$collection->getQueryBuilder()->orderBy(null);
		return $collection;
	}


	public function limitBy(int $limit, int $offset = null): ICollection
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


	public function fetchPairs(string $key = null, string $value = null): array
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


	public function setRelationshipMapper(IRelationshipMapper $mapper = null, IEntity $parent = null): ICollection
	{
		$this->relationshipMapper = $mapper;
		$this->relationshipParent = $parent;
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
	 * @internal
	 */
	public function getQueryBuilder(): QueryBuilder
	{
		$joins = [];
		$groupBy = [];
		$helper = $this->getHelper();
		$args = $this->filtering;

		if (count($args) > 0) {
			array_unshift($args, ICollection::AND);
			$expression = $helper->processExpression(
				builder: $this->queryBuilder,
				expression: $args,
				context: ExpressionContext::FilterAnd,
				aggregator: null,
			);
			$joins = $expression->joins;
			if ($expression->isHavingClause) {
				$groupBy = $expression->groupBy;
				$this->queryBuilder->andHaving($expression->expression, ...$expression->args);
			} else {
				$this->queryBuilder->andWhere($expression->expression, ...$expression->args);
			}
			$this->filtering = [];
		}

		foreach ($this->ordering as [$expression, $direction]) {
			$joins = array_merge($joins, $expression->joins);
			if ($expression->isHavingClause) {
				$groupBy = array_merge($groupBy, $expression->groupBy);
			}
			$orderingExpression = $helper->processOrderDirection($expression, $direction);
			$this->queryBuilder->addOrderBy('%ex', $orderingExpression);
		}
		$this->ordering = [];

		$mergedJoins = $helper->mergeJoins('%and', $joins);
		foreach ($mergedJoins as $join) {
			$join->applyJoin($this->queryBuilder);
		}

		if (count($groupBy) > 0) {
			$this->queryBuilder->groupBy(
				'%ex' . str_repeat(', %ex', count($groupBy) - 1),
				...$groupBy,
			);
		}

		return $this->queryBuilder;
	}


	protected function getIteratorCount(): int
	{
		if ($this->resultCount === null) {
			$builder = clone $this->getQueryBuilder();
			if (!$builder->hasLimitOffsetClause()) {
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

			$this->resultCount = $this->connection->queryArgs($sql, $args)->fetchField();
		}

		return $this->resultCount;
	}


	protected function execute(): void
	{
		$result = $this->connection->queryByQueryBuilder($this->getQueryBuilder());

		$this->result = [];
		while ($data = $result->fetch()) {
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
}
