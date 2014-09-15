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
	protected $updatingReverseRelationship = FALSE;

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


	public function isLoaded()
	{
		return $this->value !== FALSE;
	}


	public function getInjectedValue()
	{
		return $this->getEntity();
	}


	public function getRawValue()
	{
		return $this->getPrimaryValue();
	}


	public function set($value, $forceNULL = FALSE)
	{
		if ($this->updatingReverseRelationship) {
			return NULL;
		}

		$value = $this->createEntity($value, $forceNULL);

		if ($this->isChanged($value)) {
			$this->isModified = TRUE;
			$oldValue = $this->value;
			if ($oldValue === FALSE) {
				$primaryValue = $this->getPrimaryValue();
				$oldValue = $primaryValue !== NULL ? $this->getTargetRepository()->getById($primaryValue) : NULL;
			}
			$this->updateRelationship($oldValue, $value);
		}

		$this->primaryValue = $value && $value->isPersisted() ? $value->id : NULL;
		$this->value = $value;
	}


	public function getEntity($collectionName = NULL)
	{
		if ($this->value === FALSE) {
			if (!$this->parent->isPersisted() || $this->primaryValue === NULL) {
				return NULL;
			}

			$collection = $this->getCachedCollection($collectionName);
			$entity = $collection->getEntityIterator($this->parent)[0];
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
			$this->targetRepository = $this->parent->getRepository()->getModel()->getRepository($this->propertyMeta->relationshipRepository);
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


	protected function createEntity($value, $forceNULL)
	{
		if ($value instanceof IEntity) {
			if ($model = $this->parent->getModel(FALSE)) {
				$repo = $model->getRepository($this->propertyMeta->relationshipRepository);
				$repo->attach($value);

			} elseif ($model = $value->getModel(FALSE)) {
				$repository = $model->getRepositoryForEntity($this->parent);
				$repository->attach($this->parent);
			}

		} elseif ($value === NULL) {
			if (!$this->propertyMeta->isNullable && !$forceNULL) {
				$class = get_class($this->parent);
				throw new InvalidArgumentException("Property {$class}::\${$this->propertyMeta->name} is not nullable.");
			}

		} elseif (is_scalar($value)) {
			$value = $this->getTargetRepository()->getById($value);

		} else {
			throw new InvalidArgumentException('Value is not a valid entity representation.');
		}

		return $value;
	}


	protected function isChanged($newValue)
	{
		if ($newValue instanceof IEntity && $this->value instanceof IEntity) {
			return $newValue !== $this->value;

		} elseif ($newValue instanceof IEntity && $newValue->isPersisted()) {
			return (string) $this->getPrimaryValue() !== (string) $newValue->id;

		} else {
			return $newValue !== $this->value;
		}
	}


	abstract protected function updateRelationship($oldEntity, $newEntity);

}
