<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nette\Object;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\EmptyCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;


abstract class HasMany extends Object implements IRelationshipCollection
{
	/** @var IEntity */
	protected $parent;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var ICollection */
	protected $collection;

	/** @var IEntity[] */
	protected $toAdd = [];

	/** @var IEntity[] */
	protected $toRemove = [];

	/** @var IEntity[] */
	protected $added = [];

	/** @var IEntity[] */
	protected $removed = [];

	/** @var IRepository */
	protected $targetRepository;

	/** @var bool */
	protected $updatingReverseRelationship = false;

	/** @var bool */
	protected $isModified = false;

	/** @var bool */
	protected $wasLoaded = false;

	/** @var IRelationshipMapper */
	protected $relationshipMapper;


	public function __construct(IEntity $parent, PropertyMetadata $metadata)
	{
		$this->parent = $parent;
		$this->metadata = $metadata;
	}


	public function setParent(IEntity $parent)
	{
		$this->parent = $parent;
	}


	public function setRawValue($value)
	{
		if ($value !== null) { // null passed when property is initialized
			$this->set($value);
		}
	}


	public function setInjectedValue($value)
	{
		$this->set($value);
	}


	public function add($entity)
	{
		if ($this->updatingReverseRelationship) {
			return null;
		}

		$entity = $this->createEntity($entity);
		$entityHash = spl_object_hash($entity);

		if (isset($this->toRemove[$entityHash])) {
			unset($this->toRemove[$entityHash]);
		} else {
			$this->toAdd[$entityHash] = $entity;
		}

		$this->updateRelationshipAdd($entity);
		$this->modify();
		$this->wasLoaded = $this->wasLoaded || $this->collection !== null;
		$this->collection = null;
		return $entity;
	}


	public function remove($entity)
	{
		if ($this->updatingReverseRelationship) {
			return null;
		}

		$entity = $this->createEntity($entity);
		$entityHash = spl_object_hash($entity);

		if (isset($this->toAdd[$entityHash])) {
			unset($this->toAdd[$entityHash]);
		} else {
			$this->toRemove[$entityHash] = $entity;
			unset($this->added[$entityHash]);
		}

		$this->updateRelationshipRemove($entity);
		$this->modify();
		$this->wasLoaded = $this->wasLoaded || $this->collection !== null;
		$this->collection = null;
		return $entity;
	}


	public function has($entity)
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


	public function set(array $data)
	{
		foreach ($this->getCollection() as $entity) {
			$this->remove($entity);
		}

		foreach ($data as $entity) {
			$this->add($entity);
		}

		return $this;
	}


	public function get()
	{
		return clone $this->getCollection(true);
	}


	public function count()
	{
		return iterator_count($this->getIterator());
	}


	public function countStored()
	{
		/** @var ICollection $collection */
		$collection =
			$this->collection === null
			&& !$this->toAdd
			&& !$this->toRemove
			&& !$this->added
			&& !$this->removed
			&& $this->parent->isPersisted()
			&& $this->parent->getPreloadContainer()
			? $this->getCachedCollection()
			: $this->getCollection();
		return $collection->getEntityCount($this->parent);
	}


	/**
	 * @return ICollection|IEntity[]|\Traversable
	 */
	public function getIterator()
	{
		/** @var ICollection $collection */
		$collection =
			$this->collection === null
			&& !$this->toAdd
			&& !$this->toRemove
			&& !$this->added
			&& !$this->removed
			&& $this->parent->isPersisted()
			&& $this->parent->getPreloadContainer()
			? $this->getCachedCollection()
			: $this->getCollection();
		return $collection->getEntityIterator($this->parent);
	}


	public function isLoaded()
	{
		return $this->collection !== null || !empty($this->toAdd) || !empty($this->toRemove) || !empty($this->added) || !empty($this->removed);
	}


	public function isModified()
	{
		return $this->isModified;
	}


	/**
	 * Returns primary values of enitities in relationship.
	 * @return mixed[]
	 */
	public function getRawValue()
	{
		$primaryValues = [];
		foreach ($this->getIterator() as $entity) {
			if ($entity->isPersisted()) {
				$primaryValues[] = $entity->getValue('id');
			}
		}
		return $primaryValues;
	}


	/**
	 * @internal
	 * @ignore
	 * @param IEntity $entity
	 */
	public function initReverseRelationship(IEntity $entity)
	{
		$this->added[spl_object_hash($entity)] = $entity;
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

		if ($this->toAdd || $this->toRemove || $this->added || $this->removed) {
			$all = [];
			foreach ($collection as $entity) {
				$all[spl_object_hash($entity)] = $entity;
			}
			foreach ($this->added as $hash => $entity) {
				$all[$hash] = $entity;
			}
			foreach ($this->removed as $hash => $entity) {
				unset($all[$hash]);
			}
			foreach ($this->toAdd as $hash => $entity) {
				$all[$hash] = $entity;
			}
			foreach ($this->toRemove as $hash => $entity) {
				unset($all[$hash]);
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
	 * @return ICollection
	 */
	protected function getCachedCollection()
	{
		$key = spl_object_hash($this->parent->getPreloadContainer()) . '_' . $this->metadata->name;
		$cache = $this->parent->getRepository()->getMapper()->getCollectionCache();
		if (!isset($cache->$key)) {
			$cache->$key = $this->createCollection();
		}
		$this->collection = $cache->$key;
		return $cache->$key;
	}


	/**
	 * @param  IEntity|mixed    $entity
	 * @param  bool             $need
	 * @return IEntity
	 */
	protected function createEntity($entity, $need = true)
	{
		if ($entity instanceof IEntity) {
			if ($model = $entity->getModel(false)) {
				$repository = $model->getRepositoryForEntity($this->parent);
				$repository->attach($this->parent);

			} elseif ($model = $this->parent->getModel(false)) {
				$repository = $model->getRepositoryForEntity($entity);
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
		$this->added = [];
		$this->removed = [];
		$this->wasLoaded = false;
		$this->isModified = false;
		$this->collection = null;
	}


	/**
	 * @return IRepository
	 */
	protected function getTargetRepository()
	{
		if (!$this->targetRepository) {
			$this->targetRepository = $this->parent->getModel()->getRepository($this->metadata->relationship->repository);
		}

		return $this->targetRepository;
	}


	protected function getRelationshipMapper()
	{
		if (!$this->relationshipMapper) {
			$this->relationshipMapper = $this->createCollection()->getRelationshipMapper();
		}

		return $this->relationshipMapper;
	}


	protected function applyDefaultOrder(ICollection $collection)
	{
		if ($this->metadata->relationship->order !== null) {
			return $collection->orderBy($this->metadata->relationship->order);
		} else {
			return $collection;
		}
	}


	/**
	 * Sets relationship (and entity) as modified.
	 * @return void
	 */
	abstract protected function modify();


	/**
	 * Returns collection for has many relationship.
	 * @return ICollection
	 */
	abstract protected function createCollection();


	/**
	 * Updates relationship change for the $entity.
	 * @param  IEntity $entity
	 * @return void
	 */
	abstract protected function updateRelationshipAdd(IEntity $entity);


	/**
	 * Updates relationship change for the $entity.
	 * @param  IEntity $entity
	 * @return void
	 */
	abstract protected function updateRelationshipRemove(IEntity $entity);
}
