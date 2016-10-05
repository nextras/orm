<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nette\Object;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\NullValueException;
use Nextras\Orm\Repository\IRepository;


abstract class HasOne extends Object implements IRelationshipContainer
{
	/** @var IEntity */
	protected $parent;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var ICollection */
	protected $collection;

	/** @var mixed */
	protected $primaryValue;

	/** @var IEntity|null|false */
	protected $value = false;

	/** @var IRepository */
	protected $targetRepository;

	/** @var bool */
	protected $updatingReverseRelationship = false;

	/** @var bool */
	protected $isModified;

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
		$this->primaryValue = $value;
	}


	public function getRawValue()
	{
		return $this->getPrimaryValue();
	}


	public function setInjectedValue($value)
	{
		$this->set($value);
	}


	public function &getInjectedValue()
	{
		$value = $this->getEntity(false);
		return $value;
	}


	public function hasInjectedValue()
	{
		return $this->getEntity(true) !== null;
	}


	public function isLoaded()
	{
		return $this->value !== false;
	}


	public function set($value, $allowNull = false)
	{
		if ($this->updatingReverseRelationship) {
			return null;
		}

		$value = $this->createEntity($value, $allowNull);

		if ($this->isChanged($value)) {
			$this->modify();
			$oldValue = $this->value;
			if ($oldValue === false) {
				$primaryValue = $this->getPrimaryValue();
				$oldValue = $primaryValue !== null ? $this->getTargetRepository()->getById($primaryValue) : null;
			}
			$this->updateRelationship($oldValue, $value, $allowNull);

		} else {
			$this->initReverseRelationship($value);
		}

		$this->primaryValue = $value && $value->isPersisted() ? $value->getValue('id') : null;
		$this->value = $value;
	}


	public function getEntity($allowNull = false)
	{
		if ($this->value === false) {
			if (!$this->parent->isPersisted()) {
				$entity = null;
			} else {
				$collection = $this->getCachedCollection();
				$entity = $collection->getEntityIterator($this->parent)[0];
			}

			$this->set($entity, $allowNull);
		}

		if ($this->value === null && !$this->metadata->isNullable && !$allowNull) {
			throw new NullValueException($this->parent, $this->metadata);
		}

		return $this->value;
	}


	public function isModified()
	{
		return $this->isModified;
	}


	protected function getPrimaryValue()
	{
		if (!$this->primaryValue && $this->value && $this->value->isPersisted()) {
			$this->primaryValue = $this->value->getValue('id');
		}

		return $this->primaryValue;
	}


	protected function getTargetRepository()
	{
		if (!$this->targetRepository) {
			$this->targetRepository = $this->parent->getRepository()->getModel()->getRepository($this->metadata->relationship->repository);
		}

		return $this->targetRepository;
	}


	/**
	 * @return ICollection
	 */
	protected function getCachedCollection()
	{
		if ($this->collection !== null) {
			return $this->collection;

		} elseif ($this->parent->getPreloadContainer()) {
			$key = spl_object_hash($this->parent->getPreloadContainer()) . '_' . $this->metadata->name;
			$cache = $this->parent->getRepository()->getMapper()->getCollectionCache();
			if (!isset($cache->$key)) {
				$cache->$key = $this->createCollection();
			}
			$collection = $cache->$key;

		} else {
			$collection = $this->createCollection();
		}

		return $this->collection = $collection;
	}


	protected function createEntity($value, $allowNull)
	{
		if ($value instanceof IEntity) {
			if ($model = $this->parent->getModel(false)) {
				$repo = $model->getRepository($this->metadata->relationship->repository);
				$repo->attach($value);

			} elseif ($model = $value->getModel(false)) {
				$repository = $model->getRepositoryForEntity($this->parent);
				$repository->attach($this->parent);
			}

		} elseif ($value === null) {
			if (!$this->metadata->isNullable && !$allowNull) {
				throw new NullValueException($this->parent, $this->metadata);
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
		// newValue is IEntity or null

		if ($this->value instanceof IEntity && $newValue instanceof IEntity) {
			return $this->value !== $newValue;

		} elseif ($this->value instanceof IEntity) {
			// value is some entity
			// newValue is null
			return true;

		} elseif ($newValue instanceof IEntity && $newValue->isPersisted()) {
			// value is persited entity or null
			// newValue is persisted entity
			return (string) $this->getPrimaryValue() !== (string) $newValue->getValue('id');

		} else {
			// value is persisted entity or null
			// newValue is null
			return $this->getPrimaryValue() !== $newValue;
		}
	}


	/**
	 * Creates relationship collection.
	 * @return ICollection
	 */
	abstract protected function createCollection();


	/**
	 * Sets relationship (and entity) as modified.
	 * @return void
	 */
	abstract protected function modify();


	/**
	 * Updates relationship on the other side.
	 * @param  IEntity|null $oldEntity
	 * @param  IEntity|null $newEntity
	 * @param  bool $allowNull
	 * @return void
	 */
	abstract protected function updateRelationship($oldEntity, $newEntity, $allowNull);


	/**
	 * @param  IEntity|null $currentEntity
	 * @return mixed
	 */
	abstract protected function initReverseRelationship($currentEntity);
}
