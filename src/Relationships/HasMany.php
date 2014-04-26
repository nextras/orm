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
use Nextras\Orm\Entity\IPropertyInjection;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\CollectionMapper\ICollectionMapperHasMany;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\InvalidStateException;


abstract class HasMany extends Object implements IPropertyInjection, IRelationshipCollection
{
	/** @var IEntity */
	protected $parent;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var ICollection */
	protected $collection;

	/** @var array */
	protected $toAdd;

	/** @var array */
	protected $toRemove;

	/** @var IRepository */
	private $targetRepository;

	/** @var mixed[] */
	private $primaryValues;


	public function __construct(IEntity $parent, PropertyMetadata $metadata)
	{
		$this->parent = $parent;
		$this->metadata = $metadata;
		$this->targetRepository = $parent->getModel()->getRepository($this->metadata->args[0]);
	}


	public function add($entity)
	{
		$entity = $this->createEntity($entity);
		$entityHash = spl_object_hash($entity);

		if (isset($this->toRemove[$entityHash])) {
			unset($this->toRemove[$entityHash]);
		} else {
			$this->toAdd[$entityHash] = $entity;
		}

		$this->collection = NULL;
		return $entity;
	}


	public function remove($entity)
	{
		$entity = $this->createEntity($entity);
		$entityHash = spl_object_hash($entity);

		if (isset($this->toAdd[$entityHash])) {
			unset($this->toAdd[$entityHash]);
		} else {
			$this->toRemove[$entityHash] = $entity;
		}

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


	public function count()
	{
		if ($this->collection === NULL) {
			return $this->getMapper()->getIteratorCount($this->parent);
		}

		return $this->getCollection()->count();
	}


	public function getIterator()
	{
		if ($this->collection === NULL) {
			return $this->getMapper()->getIterator($this->parent);
		}

		return $this->getCollection()->getIterator();
	}


	public function setInjectedValue($values)
	{
		$this->primaryValues = $values;
	}


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


	protected function createEntity($entity, $need = TRUE)
	{
		if ($entity instanceof IEntity) {
			if ($model = $entity->getModel(FALSE)) {
				$repository = $model->getRepositoryForEntity($this->parent);
				$this->parent->fireEvent('onAttach', array($repository));
			}

			return $entity;

		} else {
			$foundEntity = $this->targetRepository->getById($entity);
			if (!$foundEntity && $need) {
				throw new InvalidStateException("Entity with primary value '$entity' was not found.");
			}

			return $foundEntity;
		}
	}


	/**
	 * Returns collection for has many relationship.
	 * @return ICollectionMapperHasMany
	 */
	abstract protected function createCollection();


	/**
	 * Returns mapper for has many relationship.
	 * It's used only when there is no needed any collection.
	 * @return ICollectionMapperHasMany
	 */
	abstract protected function getMapper();

}

