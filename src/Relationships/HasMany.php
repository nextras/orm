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


	public function __construct(IEntity $parent, PropertyMetadata $metadata, $value)
	{
		$this->parent = $parent;
		$this->metadata = $metadata;
		$this->injectedValue = $value ? unserialize($value) : NULL;
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
		$this->collection = NULL;
		return $entity;
	}


	public function remove($entity)
	{
		if ($this->updatingReverseRelationship) {
			return NULL;
		}

		$entity = $this->createEntity($entity);
		$entityHash = spl_object_hash($entity);

		if (isset($this->toAdd[$entityHash])) {
			unset($this->toAdd[$entityHash]);
		} else {
			$this->toRemove[$entityHash] = $entity;
		}

		$this->updateRelationshipRemove($entity);
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
		return $this->getCollection()->toCollection();
	}


	public function count($collectionName = NULL)
	{
		$collection = $this->collection === NULL && !$this->toAdd && !$this->toRemove ? $this->getCachedCollection($collectionName) : $this->getCollection();
		if ($collection->getRelationshipMapper()) {
			return $collection->getRelationshipMapper()->getIteratorCount($this->parent, $collection);
		} else {
			return count($collection);
		}
	}


	public function getIterator($collectionName = NULL)
	{
		$collection = $this->collection === NULL && !$this->toAdd && !$this->toRemove ? $this->getCachedCollection($collectionName) : $this->getCollection();
		if ($collection->getRelationshipMapper()) {
			return $collection->getRelationshipMapper()->getIterator($this->parent, $collection);
		} else {
			return $collection->getIterator();
		}
	}


	public function setInjectedValue($values)
	{
		$this->set($values);
	}


	public function isLoaded()
	{
		return !($this->collection === NULL && empty($this->toAdd) && empty($this->toRemove));
	}


	/**
	 * @return ICollection
	 */
	protected function getCollection()
	{
		if ($this->collection !== NULL) {
			return $this->collection;
		}

		if ($this->parent->hasValue('id')) {
			$collection = $this->createCollection();
		} else {
			$collection = new ArrayCollection([]);
		}

		if ($this->toAdd) {
			$all = [];

			foreach ($collection as $entity) {
				$all[spl_object_hash($entity)] = $entity;
			}
			foreach ($this->toAdd as $hash => $entity) {
				$all[$hash] = $entity;
			}

			$collection = new ArrayCollection($all);
		}

		return $this->collection = $collection;
	}


	/**
	 * @param  string
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
	 * @param  IEntity|mixed
	 * @param  bool
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
			$this->targetRepository = $this->parent->getModel()->getRepository($this->metadata->args[0]);
		}

		return $this->targetRepository;
	}


	/**
	 * Returns collection for has many relationship.
	 * @return ICollection
	 */
	abstract protected function createCollection();


	/**
	 * Updates relationship change for the $entity.
	 */
	abstract protected function updateRelationshipAdd(IEntity $entity);


	/**
	 * Updates relationship change for the $entity.
	 */
	abstract protected function updateRelationshipRemove(IEntity $entity);

}
