<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Closure;
use Countable;
use Iterator;
use Nette\Utils\Arrays;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\MemberAccessException;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;
use function array_values;


/**
 * @template E of IEntity
 * @implements ICollection<E>
 * @implements MemoryCollection<E>
 */
class ArrayCollection implements ICollection, MemoryCollection
{
	/** @var list<callable(\Traversable<E> $entities): void> */
	public array $onEntityFetch = [];

	/** @var list<E> */
	protected array $data;

	protected IRelationshipMapper|null $relationshipMapper = null;
	protected IEntity|null $relationshipParent = null;

	/** @var Iterator<E>|null */
	protected Iterator|null $fetchIterator = null;

	protected ArrayCollectionHelper|null $helper = null;

	/** @var array<Closure(E): ArrayExpressionResult> */
	protected array $collectionFilter = [];

	/** @var list<array{mixed, string}> */
	protected array $collectionSorter = [];

	/** @var null|array{int, int|null} */
	protected ?array $collectionLimit = null;
	protected bool $entityFetchEventTriggered = false;


	/**
	 * @param list<E> $entities
	 * @param IRepository<E> $repository
	 */
	public function __construct(
		array $entities,
		protected readonly IRepository $repository,
	)
	{
		if (!Arrays::isList($entities)) {
			throw new InvalidArgumentException('Entities has to be passed as a list.');
		}
		$this->data = $entities;
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
		$collection->collectionFilter[] = $this->getHelper()->createFilter($conds, null);
		return $collection;
	}


	public function orderBy($expression, string $direction = self::ASC): ICollection
	{
		$collection = clone $this;
		if (is_array($expression) && !isset($expression[0])) {
			foreach ($expression as $subExpression => $subDirection) {
				$collection->collectionSorter[] = [$subExpression, $subDirection];
			}
		} else {
			$collection->collectionSorter[] = [$expression, $direction];
		}
		return $collection;
	}


	public function resetOrderBy(): ICollection
	{
		$collection = clone $this;
		$collection->collectionSorter = [];
		return $collection;
	}


	public function limitBy(int $limit, int|null $offset = null): ICollection
	{
		$collection = clone $this;
		$collection->collectionLimit = [$limit, $offset];
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
			$collection = clone $this;
			$collection->relationshipMapper = null;
			$collection->relationshipParent = null;
			/** @var Iterator<E> $entityIterator */
			$entityIterator = $this->relationshipMapper->getIterator($this->relationshipParent, $collection);
		} else {
			$this->processData();
			/** @var Iterator<E> $entityIterator */
			$entityIterator = new EntityIterator($this->data);
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
		$iterator = $this->getIterator();
		assert($iterator instanceof Countable);
		return count($iterator);
	}


	public function countStored(): int
	{
		return $this->count();
	}


	public function toMemoryCollection(): MemoryCollection
	{
		return clone $this;
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
		$this->fetchIterator = null;
		$this->entityFetchEventTriggered = false;
	}


	protected function processData(): void
	{
		if (count($this->collectionFilter) > 0 || count($this->collectionSorter) > 0 || $this->collectionLimit !== null) {
			$data = $this->data;

			foreach ($this->collectionFilter as $filter) {
				$data = array_filter($data, function ($value) use ($filter) {
					return $filter($value)->value;
				});
			}

			if (count($this->collectionSorter) > 0) {
				$sorter = $this->getHelper()->createSorter($this->collectionSorter);
				usort($data, $sorter);
			}

			if ($this->collectionLimit !== null) {
				$data = array_slice($data, $this->collectionLimit[1] ?? 0, $this->collectionLimit[0]);
			}

			$this->collectionFilter = [];
			$this->collectionSorter = [];
			$this->collectionLimit = null;
			$this->data = array_values($data);
		}
	}


	protected function getHelper(): ArrayCollectionHelper
	{
		if ($this->helper === null) {
			$this->helper = new ArrayCollectionHelper($this->repository);
		}

		return $this->helper;
	}
}
