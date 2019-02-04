<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nette\SmartObject;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\EmptyCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;


abstract class HasMany implements IRelationshipCollection
{
	use SmartObject;


	/** @var IEntity */
	protected $parent;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var PropertyRelationshipMetadata */
	protected $metadataRelationship;

	/** @var ICollection|null */
	protected $collection;

	/** @var IEntity[] */
	protected $toAdd = [];

	/** @var IEntity[] */
	protected $toRemove = [];

	/** @var IEntity[] */
	protected $added = [];

	/** @var IEntity[] */
	protected $tracked = [];

	/** @var IRepository|null */
	protected $targetRepository;

	/** @var bool */
	protected $updatingReverseRelationship = false;

	/** @var bool */
	protected $isModified = false;

	/** @var IRelationshipMapper|null */
	protected $relationshipMapper;


	public function __construct(PropertyMetadata $metadata)
	{
		assert($metadata->relationship !== null);
		$this->metadata = $metadata;
		$this->metadataRelationship = $metadata->relationship;
	}


	/**
	 * @internal
	 * @ignore
	 */
	public function setPropertyEntity(IEntity $parent)
	{
		$this->parent = $parent;
	}


	public function loadValue(IEntity $parent, array $values): void
	{
	}


	public function saveValue(IEntity $parent, array $values): array
	{
		return $values;
	}


	public function convertToRawValue($value)
	{
		if ($value instanceof IEntity) {
			return $value->getValue('id');
		}
		return $value;
	}


	public function setRawValue($value)
	{
		$this->set($value);
	}


	/**
	 * Returns primary values of enitities in relationship.
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


	public function setInjectedValue(IEntity $entity, $value)
	{
		$this->set($value);
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

		$entityHash = spl_object_hash($entity);

		if (isset($this->toRemove[$entityHash])) {
			unset($this->toRemove[$entityHash]);
		} else {
			$this->toAdd[$entityHash] = $entity;
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

		$entity = $this->createEntity($entity);
		if ($entity === null) {
			return null;
		}

		$entityHash = spl_object_hash($entity);

		if (isset($this->toAdd[$entityHash])) {
			unset($this->toAdd[$entityHash]);
		} else {
			$this->toRemove[$entityHash] = $entity;
			unset($this->tracked[$entityHash]);
		}

		$this->updateRelationshipRemove($entity);
		$this->modify();
		$this->collection = null;
		return $entity;
	}


	public function has($entity): bool
	{
		$entity = $this->createEntity($entity, false);
		if (!$entity) {
			return false;
		}

		$entityHash = spl_object_hash($entity);
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


	public function set(array $data): IRelationshipCollection
	{
		foreach ($this->getCollection() as $entity) {
			$this->remove($entity);
		}

		foreach ($data as $entity) {
			$this->add($entity);
		}

		return $this;
	}


	public function get(): ICollection
	{
		return clone $this->getCollection(true);
	}


	public function count()
	{
		return iterator_count($this->getIterator());
	}


	public function countStored(): int
	{
		return $this->getIterator()->countStored();
	}


	/**
	 * @return ICollection|IEntity[]
	 */
	public function getIterator(): ICollection
	{
		return $this->getCollection();
	}


	public function isLoaded(): bool
	{
		return $this->collection !== null || !empty($this->toAdd) || !empty($this->toRemove) || !empty($this->tracked);
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
		$this->tracked[spl_object_hash($entity)] = $entity;
	}


	/**
	 * @return ICollection
	 */
	protected function getCollection($forceNew = false)
	{
		if ($this->collection !== null && !$forceNew) {
			return $this->collection;
		}

		if ($this->parent->isPersisted()) {
			$collection = $this->createCollection();
		} else {
			$collection = new EmptyCollection();
		}

		if ($this->toAdd || $this->toRemove) {
			$all = [];
			foreach ($collection as $entity) {
				$all[spl_object_hash($entity)] = $entity;
			}
			foreach ($this->toAdd as $hash => $entity) {
				$all[$hash] = $entity;
			}
			foreach ($this->toRemove as $hash => $entity) {
				unset($all[$hash], $this->tracked[$hash]);
			}

			$collection = new ArrayCollection(array_values($all), $this->getTargetRepository());
			$collection = $this->applyDefaultOrder($collection);
		}

		if (!$forceNew) {
			$this->collection = $collection;
		}
		return $collection;
	}


	/**
	 * @param  IEntity|mixed    $entity
	 */
	protected function createEntity($entity, bool $need = true): ?IEntity
	{
		if ($entity instanceof IEntity) {
			if ($entity->isAttached()) {
				$repository = $entity->getRepository()->getModel()->getRepositoryForEntity($this->parent);
				$repository->attach($this->parent);

			} elseif ($this->parent->isAttached()) {
				$repository = $this->parent->getRepository()->getModel()->getRepositoryForEntity($entity);
				$repository->attach($entity);
			}

			return $entity;

		} else {
			$foundEntity = $this->getTargetRepository()->getById($entity);
			if (!$foundEntity && $need) {
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


	protected function getTargetRepository(): IRepository
	{
		if (!$this->targetRepository) {
			$this->targetRepository = $this->parent->getRepository()->getModel()->getRepository($this->metadataRelationship->repository);
		}

		return $this->targetRepository;
	}


	protected function getRelationshipMapper()
	{
		if (!$this->relationshipMapper) {
			$relationshipMapper = $this->createCollection()->getRelationshipMapper();
			assert($relationshipMapper !== null);
			$this->relationshipMapper = $relationshipMapper;
		}

		return $this->relationshipMapper;
	}


	protected function applyDefaultOrder(ICollection $collection)
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
	 */
	abstract protected function createCollection(): ICollection;


	/**
	 * Updates relationship change for the $entity.
	 */
	abstract protected function updateRelationshipAdd(IEntity $entity): void;


	/**
	 * Updates relationship change for the $entity.
	 */
	abstract protected function updateRelationshipRemove(IEntity $entity): void;
}
