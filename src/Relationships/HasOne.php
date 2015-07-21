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

	/** @var mixed */
	protected $primaryValue;

	/** @var IEntity|NULL|FALSE */
	protected $value = FALSE;

	/** @var IRepository */
	protected $targetRepository;

	/** @var bool */
	protected $updatingReverseRelationship = FALSE;

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


	public function & getInjectedValue()
	{
		$value = $this->getEntity(FALSE);
		return $value;
	}


	public function hasInjectedValue()
	{
		return $this->getEntity(TRUE) !== NULL;
	}


	public function isLoaded()
	{
		return $this->value !== FALSE;
	}


	public function set($value, $allowNull = FALSE)
	{
		if ($this->updatingReverseRelationship) {
			return NULL;
		}

		$value = $this->createEntity($value, $allowNull);

		if ($this->isChanged($value)) {
			$this->modify();
			$oldValue = $this->value;
			if ($oldValue === FALSE) {
				$primaryValue = $this->getPrimaryValue();
				$oldValue = $primaryValue !== NULL ? $this->getTargetRepository()->getById($primaryValue) : NULL;
			}
			$this->updateRelationship($oldValue, $value, $allowNull);
		}

		$this->primaryValue = $value && $value->isPersisted() ? $value->id : NULL;
		$this->value = $value;
	}


	public function getEntity($allowNull = FALSE)
	{
		if ($this->value === FALSE) {
			if (!$this->parent->isPersisted()) {
				$entity = NULL;
			} else {
				$collection = $this->getCachedCollection(NULL);
				$entity = $collection->getEntityIterator($this->parent)[0];
			}

			$this->set($entity, $allowNull);
		}

		if ($this->value === NULL && !$this->metadata->isNullable && !$allowNull) {
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
			$this->primaryValue = $this->value->id;
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

		return $cache->$key;
	}


	protected function createCollection()
	{
		return $this->getTargetRepository()->getMapper()->createCollectionHasOne($this->metadata, $this->parent);
	}


	protected function createEntity($value, $allowNull)
	{
		if ($value instanceof IEntity) {
			if ($model = $this->parent->getModel(FALSE)) {
				$repo = $model->getRepository($this->metadata->relationship->repository);
				$repo->attach($value);

			} elseif ($model = $value->getModel(FALSE)) {
				$repository = $model->getRepositoryForEntity($this->parent);
				$repository->attach($this->parent);
			}

		} elseif ($value === NULL) {
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
		// newValue is IEntity or NULL

		if ($this->value instanceof IEntity && $newValue instanceof IEntity) {
			return $this->value !== $newValue;

		} elseif ($this->value instanceof IEntity) {
			// value is some entity
			// newValue is NULL
			return TRUE;

		} elseif ($newValue instanceof IEntity && $newValue->isPersisted()) {
			// value is persited entity or NULL
			// newValue is persisted entity
			return (string) $this->getPrimaryValue() !== (string) $newValue->getValue('id');

		} else {
			// value is persisted entity or NULL
			// newValue is NULL
			return $this->getPrimaryValue() !== $newValue;
		}
	}


	/**
	 * Sets relationship (and entity) as modified.
	 * @return void
	 */
	abstract protected function modify();


	/**
	 * Updates relationship on the other side.
	 * @param  IEntity|NULL $oldEntity
	 * @param  IEntity|NULL $newEntity
	 * @param  bool $allowNull
	 * @return void
	 */
	abstract protected function updateRelationship($oldEntity, $newEntity, $allowNull);

}
