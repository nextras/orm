<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;


use Nette\SmartObject;
use Nextras\Orm\Collection\EmptyCollection;
use Nextras\Orm\Collection\HasManyCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\LogicException;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;
use function array_values;
use function assert;
use function is_array;
use function iterator_count;
use function spl_object_id;


/**
 * @template E of IEntity
 * @implements IRelationshipCollection<E>
 */
abstract class HasMany implements IRelationshipCollection
{
	use SmartObject;


	/** @var E */
	protected IEntity $parent;

	/** @var ICollection<E>|null */
	protected ?ICollection $collection = null;

	/** @var array<array-key, E> */
	protected array $toAdd = [];

	/** @var array<array-key, E> */
	protected array $toRemove = [];

	/** @var array<array-key, E> */
	protected array $tracked = [];

	/** @var IRepository<E>|null */
	protected ?IRepository $targetRepository = null;

	protected bool $updatingReverseRelationship = false;
	protected bool $isModified = false;
	protected bool $exposeCollection;

	protected PropertyRelationshipMetadata $metadataRelationship;
	protected ?IRelationshipMapper $relationshipMapper = null;


	public function __construct(
		protected readonly PropertyMetadata $metadata,
	)
	{
		assert($metadata->relationship !== null);
		$this->metadataRelationship = $metadata->relationship;
		$this->exposeCollection = (bool) ($this->metadata->args[HasMany::class]['exposeCollection'] ?? false);
	}


	public function onEntityAttach(IEntity $entity): void
	{
		$this->parent = $entity;
	}


	public function onEntityRepositoryAttach(IEntity $entity): void
	{
	}


	public function convertToRawValue($value)
	{
		if ($value instanceof IEntity) {
			return $value->getValue('id');
		}
		return $value;
	}


	/**
	 * @param list<E>|list<string>|list<int>|mixed $value
	 */
	public function setRawValue($value): void
	{
		if (is_array($value)) {
			$this->set(array_values($value));
		}
	}


	/**
	 * Returns primary values of entities in relationship.
	 * @return mixed[]
	 */
	public function getRawValue(): array
	{
		$primaryValues = [];
		foreach ($this->getIterator() as $entity) {
			if ($entity->isPersisted()) {
				$primaryValues[] = $entity->getValue('id');
			}
		}
		return $primaryValues;
	}


	public function setInjectedValue($value): bool
	{
		$class = get_class($this->parent);
		throw new LogicException("You cannot set relationship collection value in $class::\${$this->metadata->name} directly.");
	}


	public function &getInjectedValue()
	{
		if ($this->exposeCollection) {
			$collection = $this->getIterator();
			return $collection;
		} else {
			return $this;
		}
	}


	public function hasInjectedValue(): bool
	{
		return true;
	}


	public function add($entity): ?IEntity
	{
		if ($this->updatingReverseRelationship) {
			return null;
		}

		$entity = $this->createEntity($entity);
		if ($entity === null) {
			return null;
		}

		$entityId = spl_object_id($entity);

		if (isset($this->toRemove[$entityId])) {
			unset($this->toRemove[$entityId]);
			$this->tracked[$entityId] = $entity;
		} else {
			$this->toAdd[$entityId] = $entity;
		}

		$this->updateRelationshipAdd($entity);
		$this->modify();
		$this->collection = null;
		return $entity;
	}


	public function remove($entity): ?IEntity
	{
		if ($this->updatingReverseRelationship) {
			return null;
		}

		$entity = $this->createEntity($entity, attach: false);
		if ($entity === null) {
			return null;
		}

		$entityId = spl_object_id($entity);

		if (isset($this->toAdd[$entityId])) {
			unset($this->toAdd[$entityId]);
		} else {
			$this->toRemove[$entityId] = $entity;
			unset($this->tracked[$entityId]);
		}

		$this->updateRelationshipRemove($entity);
		$this->modify();
		$this->collection = null;
		return $entity;
	}


	public function has($entity): bool
	{
		$entity = $this->createEntity($entity, need: false, attach: false);
		if ($entity === null) {
			return false;
		}

		$entityHash = spl_object_id($entity);
		if (isset($this->toAdd[$entityHash])) {
			return true;

		} elseif (isset($this->toRemove[$entityHash])) {
			return false;

		} elseif (!$entity->isPersisted()) {
			return false;

		} else {
			return (bool) $this->getCollection()->getBy(['id' => $entity->getValue('id')]);
		}
	}


	public function set(array $data): bool
	{
		$wanted = [];
		foreach ($data as $entry) {
			$entity = $this->createEntity($entry);
			if ($entity === null) continue;
			$wanted[spl_object_id($entity)] = $entity;
		}

		$current = [];
		foreach ($this->getCollection() as $entity) {
			$current[spl_object_id($entity)] = $entity;
		}

		$toRemove = array_diff_key($current, $wanted);
		$toAdd = array_diff_key($wanted, $current);

		foreach ($toRemove as $entity) {
			$this->remove($entity);
		}

		foreach ($toAdd as $entity) {
			$this->add($entity);
		}

		return true;
	}


	public function toCollection(): ICollection
	{
		return clone $this->getCollection(true);
	}


	public function count(): int
	{
		return iterator_count($this->getIterator());
	}


	public function countStored(): int
	{
		return $this->getIterator()->countStored();
	}


	/**
	 * @return ICollection<E>
	 */
	public function getIterator(): ICollection
	{
		return $this->getCollection();
	}


	public function isLoaded(): bool
	{
		return $this->collection !== null || count($this->toAdd) > 0 || count($this->toRemove) > 0 || count($this->tracked) > 0;
	}


	public function isModified(): bool
	{
		return $this->isModified;
	}


	/**
	 * @internal
	 * @ignore
	 */
	public function trackEntity(IEntity $entity): void
	{
		$this->tracked[spl_object_id($entity)] = $entity;
	}


	/**
	 * @return ICollection<E>
	 */
	protected function getCollection(bool $forceNew = false): ICollection
	{
		if ($this->collection !== null && !$forceNew) {
			return $this->collection;
		}

		if ($this->parent->isPersisted()) {
			$collection = $this->createCollection();
		} else {
			/** @var ICollection<E> $collection */
			$collection = new EmptyCollection();
		}

		if (count($this->toAdd) > 0 || count($this->toRemove) > 0) {
			$collection = $collection->resetOrderBy();
			/** @var ICollection<E> $collection */
			$collection = new HasManyCollection(
				$this->getTargetRepository(),
				$collection,
				function (): array {
					return [$this->toAdd, $this->toRemove];
				}
			);
			$collection = $this->applyDefaultOrder($collection);
		}

		if (!$forceNew) {
			$this->collection = $collection;
		}
		return $collection;
	}


	/**
	 * @param E|string|int $entity
	 * @return E|null
	 */
	protected function createEntity($entity, bool $need = true, bool $attach = true): ?IEntity
	{
		if ($entity instanceof IEntity) {
			if ($attach) {
				if ($entity->isAttached()) {
					$repository = $entity->getRepository()->getModel()->getRepositoryForEntity($this->parent);
					$repository->attach($this->parent);

				} elseif ($this->parent->isAttached()) {
					$repository = $this->parent->getRepository()->getModel()->getRepositoryForEntity($entity);
					$repository->attach($entity);
				}
			}

			return $entity;

		} else {
			$foundEntity = $this->getTargetRepository()->getById($entity);
			if ($foundEntity === null && $need) {
				throw new InvalidStateException("Entity with primary value '$entity' was not found.");
			}

			return $foundEntity;
		}
	}


	public function __clone()
	{
		$this->tracked = [];
		$this->isModified = false;
		$this->collection = null;
	}


	/**
	 * @return IRepository<E>
	 */
	protected function getTargetRepository(): IRepository
	{
		if ($this->targetRepository === null) {
			/** @var IRepository<E> $repository */
			$repository = $this->parent->getRepository()->getModel()
				->getRepository($this->metadataRelationship->repository);
			$this->targetRepository = $repository;
		}

		return $this->targetRepository;
	}


	protected function getRelationshipMapper(): IRelationshipMapper
	{
		if ($this->relationshipMapper === null) {
			$relationshipMapper = $this->createCollection()->getRelationshipMapper();
			assert($relationshipMapper !== null);
			$this->relationshipMapper = $relationshipMapper;
		}

		return $this->relationshipMapper;
	}


	/**
	 * @param ICollection<E> $collection
	 * @return ICollection<E>
	 */
	protected function applyDefaultOrder(ICollection $collection): ICollection
	{
		if ($this->metadataRelationship->order !== null) {
			return $collection->orderBy($this->metadataRelationship->order);
		} else {
			return $collection;
		}
	}


	/**
	 * Sets relationship (and entity) as modified.
	 */
	abstract protected function modify(): void;


	/**
	 * Returns collection for has many relationship.
	 * @return ICollection<E>
	 */
	abstract protected function createCollection(): ICollection;


	/**
	 * Updates relationship change for the $entity.
	 * @param E $entity
	 */
	abstract protected function updateRelationshipAdd(IEntity $entity): void;


	/**
	 * Updates relationship change for the $entity.
	 * @param E $entity
	 */
	abstract protected function updateRelationshipRemove(IEntity $entity): void;
}
