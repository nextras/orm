<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Iterator;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\MemberAccessException;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;
use function array_map;
use function count;
use function get_class;
use function iterator_count;
use function iterator_to_array;
use function spl_object_hash;


/**
 * @template E of IEntity
 * @implements ICollection<E>
 */
class HasManyCollection implements ICollection
{
	/**
	 * @var array of callbacks with $entities argument
	 * @phpstan-var array<callable(\Traversable<E>):void>
	 */
	public $onEntityFetch = [];

	/** @var IRepository<E> */
	private $repository;

	/**
	 * @var ICollection<IEntity>
	 * @phpstan-var ICollection<E>
	 */
	private $storageCollection;

	/**
	 * @var MutableArrayCollection
	 * @phpstan-var MutableArrayCollection<E>
	 */
	private $inMemoryCollection;

	/**
	 * @var callable A callback returning a list entities to add & remove.
	 * @phpstan-var callable(): array{array<string, E>, array<string, E>}
	 */
	private $diffCallback;

	/** @var Iterator<mixed, mixed>|null */
	private $fetchIterator;


	/**
	 * @phpstan-param IRepository<E> $repository
	 * @phpstan-param ICollection<E> $innerCollection
	 * @phpstan-param callable():array{array<string, E>, array<string, E>} $diffCallback
	 */
	public function __construct(
		IRepository $repository,
		ICollection $innerCollection,
		callable $diffCallback
	)
	{
		$this->repository = $repository;
		$this->storageCollection = $innerCollection;
		$this->diffCallback = $diffCallback;
		$this->inMemoryCollection = new MutableArrayCollection([], $repository); // @phpstan-ignore-line
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
		$collection->storageCollection = $this->storageCollection->findBy($conds);
		$collection->inMemoryCollection = $this->inMemoryCollection->findBy($conds);
		return $collection;
	}


	public function orderBy($expression, string $direction = self::ASC): ICollection
	{
		$collection = clone $this;
		$collection->storageCollection = $this->storageCollection->orderBy($expression, $direction);
		$collection->inMemoryCollection = $this->inMemoryCollection->orderBy($expression, $direction);
		return $collection;
	}


	public function resetOrderBy(): ICollection
	{
		$collection = clone $this;
		$collection->storageCollection = $this->storageCollection->resetOrderBy();
		$collection->inMemoryCollection = $this->inMemoryCollection->resetOrderBy();
		return $collection;
	}


	public function limitBy(int $limit, int $offset = null): ICollection
	{
		$collection = clone $this;
		$collection->storageCollection = $this->storageCollection->limitBy($limit, $offset);
		$collection->inMemoryCollection = $this->inMemoryCollection->limitBy($limit, $offset);
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


	public function fetchAll()
	{
		return iterator_to_array($this->getIterator());
	}


	public function fetchPairs(string $key = null, string $value = null): array
	{
		return FetchPairsHelper::process($this->getIterator(), $key, $value);
	}


	/**
	 * @phpstan-return Iterator<E>
	 */
	public function getIterator(): Iterator
	{
		[$toAdd, $toRemove] = ($this->diffCallback)();

		$storageCollection = $this->storageCollection;
		// condition here is to maintain relationship cache
		if (count($toRemove) !== 0) {
			$toRemoveIds = array_map(function (IEntity $entity) {
				return $entity->getValue('id');
			}, $toRemove);
			$storageCollection = $storageCollection->findBy(['id!=' => $toRemoveIds]);
		}

		$all = [];
		foreach ($storageCollection as $entity) {
			$all[spl_object_hash($entity)] = $entity;
		}
		foreach ($toAdd as $hash => $entity) {
			$all[$hash] = $entity;
		}

		$collection = $this->inMemoryCollection->withData(array_values($all));
		foreach ($this->onEntityFetch as $onEntityFetch) {
			$collection->subscribeOnEntityFetch($onEntityFetch);
		}
		return $collection->getIterator();
	}


	public function count(): int
	{
		return iterator_count($this->getIterator());
	}


	public function countStored(): int
	{
		[$toAdd, $toRemove] = ($this->diffCallback)();
		$count = $this->storageCollection->countStored();
		$count -= $this->inMemoryCollection->withData(array_values($toRemove))->countStored();
		$count += $this->inMemoryCollection->withData(array_values($toAdd))->countStored();
		return $count;
	}


	public function toMemoryCollection(): MemoryCollection
	{
		$collection = clone $this;
		$entities = $collection->fetchAll();
		return new ArrayCollection($entities, $this->repository);
	}


	public function setRelationshipMapper(IRelationshipMapper $mapper = null): ICollection
	{
		$this->storageCollection->setRelationshipMapper($mapper);
		$this->inMemoryCollection->setRelationshipMapper($mapper);
		return $this;
	}


	public function getRelationshipMapper(): ?IRelationshipMapper
	{
		return $this->storageCollection->getRelationshipMapper();
	}


	public function setRelationshipParent(IEntity $parent): ICollection
	{
		$collection = clone $this;
		$collection->storageCollection = $this->storageCollection->setRelationshipParent($parent);
		$collection->inMemoryCollection = $this->inMemoryCollection->setRelationshipParent($parent);
		return $collection;
	}


	public function subscribeOnEntityFetch(callable $callback): void
	{
		$this->storageCollection->subscribeOnEntityFetch($callback);
		$this->onEntityFetch[] = $callback;
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
}
