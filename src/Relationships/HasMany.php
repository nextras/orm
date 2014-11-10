<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Nette\Object;
use Nextras\Orm\Entity\Collection\ArrayCollection;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\InvalidStateException;


abstract class HasMany extends Object implements IRelationshipCollection
{
	/** @var IEntity */
	protected $parent;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var mixed */
	protected $injectedValue;

	/** @var ICollection */
	protected $collection;

	/** @var array */
	protected $toAdd = [];

	/** @var array */
	protected $toRemove = [];

	/** @var IRepository */
	protected $targetRepository;

	/** @var bool */
	protected $updatingReverseRelationship = FALSE;

	/** @var bool */
	protected $isModified = FALSE;


	public function __construct(IEntity $parent, PropertyMetadata $metadata, $value)
	{
		$this->parent = $parent;
		$this->metadata = $metadata;
		$this->injectedValue = $value;
	}


	public function setParent(IEntity $parent)
	{
		$this->parent = $parent;
	}


	public function add($entity)
	{
		if ($this->updatingReverseRelationship) {
			return NULL;
		}

		$entity = $this->createEntity($entity);
		$entityHash = spl_object_hash($entity);

		if (isset($this->toRemove[$entityHash])) {
			unset($this->toRemove[$entityHash]);
		} else {
			$this->toAdd[$entityHash] = $entity;
		}

		$this->updateRelationshipAdd($entity);
		$this->isModified = TRUE;
		$this->collection = NULL;
		return $entity;
	}


	public function remove($entity, $isRemoved = FALSE)
	{
		if ($this->updatingReverseRelationship) {
			return NULL;
		}

		$entity = $this->createEntity($entity);
		$entityHash = spl_object_hash($entity);

		if (isset($this->toAdd[$entityHash])) {
			unset($this->toAdd[$entityHash]);
		} elseif (!$isRemoved) {
			// do not store removing entity for persisting its changes
			$this->toRemove[$entityHash] = $entity;
		}

		$this->updateRelationshipRemove($entity);
		$this->isModified = TRUE;
		$this->collection = NULL;
		return $entity;
	}


	public function has($entity)
	{
		$entity = $this->createEntity($entity, FALSE);
		if (!$entity) {
			return FALSE;
		}

		$entityHash = spl_object_hash($entity);
		if (isset($this->toAdd[$entityHash])) {
			return TRUE;

		} elseif (isset($this->toRemove[$entityHash])) {
			return FALSE;

		} elseif (!$entity->isPersisted()) {
			return FALSE;

		} else {
			return (bool) $this->getCollection()->getById($entity->id);
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
		return $this->getCollection(TRUE)->toCollection();
	}


	public function count()
	{
		return iterator_count($this->getIterator());
	}


	public function countStored()
	{
		/** @var ICollection $collection */
		$collection = $this->collection === NULL && !$this->toAdd && !$this->toRemove ? $this->getCachedCollection(NULL) : $this->getCollection();
		return $collection->getEntityCount($this->parent);
	}


	public function getIterator()
	{
		/** @var ICollection $collection */
		$collection = $this->collection === NULL && !$this->toAdd && !$this->toRemove ? $this->getCachedCollection(NULL) : $this->getCollection();
		return $collection->getEntityIterator($this->parent);
	}


	public function setInjectedValue($values)
	{
		$this->set($values);
	}


	public function isLoaded()
	{
		return !($this->collection === NULL && empty($this->toAdd) && empty($this->toRemove));
	}


	public function isModified()
	{
		return $this->isModified;
	}


	/**
	 * @return ICollection
	 */
	protected function getCollection($forceNew = FALSE)
	{
		if ($this->collection !== NULL && !$forceNew) {
			return $this->collection;
		}

		if ($this->parent->isPersisted()) {
			$collection = $this->createCollection();
		} else {
			$collection = new ArrayCollection([]);
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
				unset($all[$hash]);
			}

			$collection = new ArrayCollection(array_values($all));
			$collection = $this->applyDefaultOrder($collection);
		}

		return $this->collection = $collection;
	}


	/**
	 * @param  string   $collectionName
	 * @return ICollection
	 */
	protected function getCachedCollection($collectionName)
	{
		$key = $this->metadata->name . '_' . $collectionName;
		$cache = $this->parent->getRepository()->getMapper()->getCollectionCache();
		if (isset($cache->$key)) {
			return $cache->$key;
		}

		if ($collectionName !== NULL) {
			$filterMethod = 'filter' . $collectionName;
			$cache->$key = call_user_func([$this->parent, $filterMethod], $this->createCollection());
		} else {
			$cache->$key = $this->createCollection();
		}

		if (!$collectionName) {
			$this->collection = $cache->$key;
		}
		return $cache->$key;
	}


	/**
	 * @param  IEntity|mixed    $entity
	 * @param  bool             $need
	 * @return IEntity
	 */
	protected function createEntity($entity, $need = TRUE)
	{
		if ($entity instanceof IEntity) {
			if ($model = $entity->getModel(FALSE)) {
				$repository = $model->getRepositoryForEntity($this->parent);
				$repository->attach($this->parent);

			} elseif ($model = $this->parent->getModel(FALSE)) {
				$repository = $model->getRepositoryForEntity($entity);
				$repository->attach($entity);

			} else {
				throw new InvalidStateException('At least one entity has to be attached to IRepository.');
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
		$this->collection = NULL;
	}


	/**
	 * @return IRepository
	 */
	protected function getTargetRepository()
	{
		if (!$this->targetRepository) {
			$this->targetRepository = $this->parent->getModel()->getRepository($this->metadata->relationshipRepository);
		}

		return $this->targetRepository;
	}


	protected function applyDefaultOrder(ICollection $collection)
	{
		if (isset($this->metadata->args->relationship['order'])) {
			return $collection->orderBy($this->metadata->args->relationship['order'][0], $this->metadata->args->relationship['order'][1]);
		} else {
			return $collection;
		}
	}


	/**
	 * Returns collection for has many relationship.
	 * @return ICollection
	 */
	abstract protected function createCollection();


	/**
	 * Updates relationship change for the $entity.
	 * @param  IEntity $entity
	 */
	abstract protected function updateRelationshipAdd(IEntity $entity);


	/**
	 * Updates relationship change for the $entity.
	 * @param  IEntity $entity
	 */
	abstract protected function updateRelationshipRemove(IEntity $entity);

}
