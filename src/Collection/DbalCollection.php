<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Iterator;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\MemberAccessException;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\IRelationshipMapper;
use function count;
use function is_array;


class DbalCollection implements ICollection
{
	/**
	 * @var callable[]
	 * @phpstan-var list<callable(\Traversable<\Nextras\Orm\Entity\IEntity> $entities): void>
	 */
	public $onEntityFetch = [];

	/** @var IRelationshipMapper|null */
	protected $relationshipMapper;

	/** @var IEntity|null */
	protected $relationshipParent;

	/** @var null|Iterator<IEntity> */
	protected $fetchIterator;

	/** @var DbalMapper */
	protected $mapper;

	/** @var IConnection */
	protected $connection;

	/** @var QueryBuilder */
	protected $queryBuilder;

	/** @var DbalQueryBuilderHelper */
	protected $helper;

	/**
	 * @var array|null
	 * @phpstan-var list<IEntity>|null
	 */
	protected $result;

	/** @var int|null */
	protected $resultCount;

	/** @var bool */
	protected $entityFetchEventTriggered = false;


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


	/** {@inheritDoc} */
	public function getByChecked(array $conds): IEntity
	{
		$entity = $this->getBy($conds);
		if ($entity === null) {
			throw new NoResultException();
		}
		return $entity;
	}


	public function getById($id): ?IEntity
	{
		return $this->getBy(['id' => $id]);
	}


	/** {@inheritdoc} */
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
		$expression = $collection->getHelper()->processFilterFunction($collection->queryBuilder, $conds);
		if ($expression->isHavingClause) {
			$collection->queryBuilder->andHaving(...$expression->args);
		} else {
			$collection->queryBuilder->andWhere(...$expression->args);
		}
		return $collection;
	}


	public function orderBy($expression, string $direction = ICollection::ASC): ICollection
	{
		$collection = clone $this;
		if (is_array($expression) && !isset($expression[0])) {
			/** @phpstan-var array<string, string> $expression */
			$expression = $expression; // no-op for PHPStan

			foreach ($expression as $subExpression => $subDirection) {
				$orderArgs = $collection->getHelper()
					->processOrder($collection->queryBuilder, $subExpression, $subDirection);
				$collection->queryBuilder->addOrderBy('%ex', $orderArgs);
			}
		} else {
			/** @phpstan-var string|list<mixed> $expression */
			$expression = $expression; // no-op for PHPStan

			$orderArgs = $collection->getHelper()->processOrder($collection->queryBuilder, $expression, $direction);
			$collection->queryBuilder->addOrderBy('%ex', $orderArgs);
		}
		return $collection;
	}


	public function resetOrderBy(): ICollection
	{
		$collection = clone $this;
		$collection->queryBuilder->orderBy(null);
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


	public function fetchAll()
	{
		return iterator_to_array($this->getIterator());
	}


	public function fetchPairs(string $key = null, string $value = null): array
	{
		return FetchPairsHelper::process($this->getIterator(), $key, $value);
	}


	/**
	 * @param mixed[] $args
	 * @phpstan-return never
	 * @throws MemberAccessException
	 */
	public function __call(string $name, array $args)
	{
		$class = get_class($this);
		throw new MemberAccessException("Call to undefined method $class::$name().");
	}


	public function getIterator(): Iterator
	{
		if ($this->relationshipParent !== null && $this->relationshipMapper !== null) {
			$entityIterator = $this->relationshipMapper->getIterator($this->relationshipParent, $this);
		} else {
			if ($this->result === null) {
				$this->execute();
			}

			assert(is_array($this->result));
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
	 * @return QueryBuilder
	 * @internal
	 */
	public function getQueryBuilder()
	{
		return $this->queryBuilder;
	}


	protected function getIteratorCount(): int
	{
		if ($this->resultCount === null) {
			$builder = clone $this->queryBuilder;
			if (!$builder->hasLimitOffsetClause()) {
				$builder->orderBy(null);
			}

			$select = $builder->getClause('select')[0];
			if (is_array($select) && count($select) === 1 && $select[0] === "[{$builder->getFromAlias()}.*]") {
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
		$builder = clone $this->queryBuilder;

		$result = $this->connection->queryArgs(
			$builder->getQuerySql(),
			$builder->getQueryParameters()
		);

		$this->result = [];
		while ($data = $result->fetch()) {
			$this->result[] = $this->mapper->hydrateEntity($data->toArray());
		}
	}


	protected function getHelper(): DbalQueryBuilderHelper
	{
		if ($this->helper === null) {
			$repository = $this->mapper->getRepository();
			$this->helper = new DbalQueryBuilderHelper($repository->getModel(), $repository, $this->mapper);
		}

		return $this->helper;
	}
}
