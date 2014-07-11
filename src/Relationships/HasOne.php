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
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Repository\IRepository;


abstract class HasOne extends Object implements IRelationshipContainer
{
	/** @var IEntity */
	protected $parent;

	/** @var PropertyMetadata */
	protected $propertyMeta;

	/** @var IRepository */
	protected $targetRepository;

	/** @var mixed */
	protected $primaryValue;

	/** @var IEntity|NULL|bool */
	protected $value = FALSE;

	/** @var bool */
	protected $isModified;


	public function __construct(IEntity $parent, PropertyMetadata $propertyMeta, $value)
	{
		$this->parent = $parent;
		$this->propertyMeta = $propertyMeta;
		$this->primaryValue = $value;
	}


	public function setParent(IEntity $parent)
	{
		$this->parent = $parent;
	}


	public function setInjectedValue($value)
	{
		$this->set($value);
	}


	public function getPrimaryValue()
	{
		if (!$this->primaryValue && $this->value && $this->value->isPersisted()) {
			$this->primaryValue = $this->value->id;
		}

		return $this->primaryValue;
	}


	public function getInjectedValue()
	{
		return $this->getEntity();
	}


	public function set($value)
	{
		$value = $this->createEntity($value);

		if ($this->isModified = $this->isChanged($value)) {
			$oldValue = $this->primaryValue !== NULL ? $this->getTargetRepository()->getById($this->primaryValue) : NULL;
			$this->updateRelationship($oldValue, $value);
		}

		$this->primaryValue = $value && $value->isPersisted() ? $value->id : NULL;
		$this->value = $value;
	}


	public function getEntity($collectionName = NULL)
	{
		if ($this->value === FALSE) {
			if (!$this->parent->isPersisted()) {
				return NULL;
			}

			$collection = $this->getCachedCollection($collectionName);
			$entity = $collection->getRelationshipMapper()->getIterator($this->parent, $collection)[0];
			$this->set($entity);
		}

		return $this->value;
	}


	public function isModified()
	{
		return $this->isModified;
	}


	protected function getTargetRepository()
	{
		if (!$this->targetRepository) {
			$this->targetRepository = $this->parent->getRepository()->getModel()->getRepository($this->propertyMeta->args[0]);
		}

		return $this->targetRepository;
	}


	/**
	 * @param  string   $collectionName
	 * @return ICollection
	 */
	protected function getCachedCollection($collectionName)
	{
		$key = $this->propertyMeta->name . '_' . $collectionName;
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

		return $cache->$key;
	}


	protected function createCollection()
	{
		return $this->getTargetRepository()->getMapper()->createCollectionHasOne($this->propertyMeta, $this->parent);
	}


	protected function createEntity($value)
	{
		if ($value instanceof IEntity) {
			if ($model = $this->parent->getModel(FALSE)) {
				$repo = $model->getRepository($this->propertyMeta->args[0]);
				$repo->attach($value);

			} elseif ($model = $value->getModel(FALSE)) {
				$repository = $model->getRepositoryForEntity($this->parent);
				$repository->attach($this->parent);
			}

		} elseif ($value === NULL) {

		} elseif (is_scalar($value)) {
			$value = $this->getTargetRepository()->getById($value);

		} else {
			throw new InvalidArgumentException('Value is not a valid entity representation.');
		}

		return $value;
	}


	protected function isChanged($newValue)
	{
		if ($newValue instanceof IEntity) {
			if (!$newValue->isPersisted()) {
				return $this->value !== $newValue;
			} else {
				return (string) $this->primaryValue !== (string) $newValue->id;
			}
		} else {
			// $newValue is NULL
			return (string) $this->primaryValue !== (string) $newValue;
		}
	}


	abstract protected function updateRelationship($oldEntity, $newEntity);

}
