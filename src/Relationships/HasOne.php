<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nette\SmartObject;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\NullValueException;
use Nextras\Orm\Repository\IRepository;


abstract class HasOne implements IRelationshipContainer
{
	use SmartObject;


	/** @var IEntity */
	protected $parent;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var ICollection */
	protected $collection;

	/** @var mixed|null */
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
		assert($metadata->relationship !== null);
		$this->parent = $parent;
		$this->metadata = $metadata;
	}


	public function setParent(IEntity $parent)
	{
		$this->parent = $parent;
	}


	public function loadValue(array $values)
	{
		$this->setRawValue($values[$this->metadata->name]);
	}


	public function saveValue(array $values): array
	{
		// raw value getter is overriden in OneHasOne
		$values[$this->metadata->name] = $this->getRawValue();
		return $values;
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


	public function hasInjectedValue(): bool
	{
		return $this->getEntity(true) !== null;
	}


	public function isLoaded(): bool
	{
		return $this->value !== false;
	}


	public function set($value, bool $allowNull = false)
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


	public function getEntity(bool $allowNull = false)
	{
		if ($this->value === false) {
			if (!$this->parent->isPersisted()) {
				$entity = null;
			} else {
				$collection = $this->getCollection();
				$entity = iterator_to_array($collection->getIterator())[0] ?? null;
			}

			$this->set($entity, $allowNull);
		}

		if ($this->value === null && !$this->metadata->isNullable && !$allowNull) {
			throw new NullValueException($this->parent, $this->metadata);
		}

		return $this->value;
	}


	public function isModified(): bool
	{
		return $this->isModified;
	}


	protected function getPrimaryValue()
	{
		if ($this->primaryValue === null && $this->value && $this->value->isPersisted()) {
			$this->primaryValue = $this->value->getValue('id');
		}

		return $this->primaryValue;
	}


	protected function getTargetRepository(): IRepository
	{
		if (!$this->targetRepository) {
			$this->targetRepository = $this->parent->getRepository()->getModel()->getRepository($this->metadata->relationship->repository);
		}

		return $this->targetRepository;
	}


	protected function getCollection(): ICollection
	{
		if ($this->collection !== null) {
			return $this->collection;
		}

		return $this->collection = $this->createCollection();
	}


	protected function createEntity($entity, bool $allowNull)
	{
		if ($entity instanceof IEntity) {
			if ($this->parent->isAttached()) {
				$repository = $this->parent->getRepository()->getModel()->getRepository($this->metadata->relationship->repository);
				$repository->attach($entity);

			} elseif ($entity->isAttached()) {
				$repository = $entity->getRepository()->getModel()->getRepositoryForEntity($this->parent);
				$repository->attach($this->parent);
			}

		} elseif ($entity === null) {
			if (!$this->metadata->isNullable && !$allowNull) {
				throw new NullValueException($this->parent, $this->metadata);
			}

		} elseif (is_scalar($entity)) {
			$entity = $this->getTargetRepository()->getById($entity);

		} else {
			throw new InvalidArgumentException('Value is not a valid entity representation.');
		}

		return $entity;
	}


	protected function isChanged($newValue): bool
	{
		// newValue is IEntity or null

		if ($this->value instanceof IEntity && $newValue instanceof IEntity) {
			return $this->value !== $newValue;

		} elseif ($this->value instanceof IEntity) {
			// value is an entity
			// newValue is null
			return true;

		} elseif ($newValue instanceof IEntity && $newValue->isPersisted()) {
			// value is persited entity or null
			// newValue is persisted entity
			return $this->getPrimaryValue() !== $newValue->getValue('id');

		} else {
			// value is persisted entity or null
			// newValue is null
			return $this->getPrimaryValue() !== $newValue;
		}
	}


	/**
	 * Creates relationship collection.
	 */
	abstract protected function createCollection(): ICollection;


	/**
	 * Sets relationship (and entity) as modified.
	 * @return void
	 */
	abstract protected function modify();


	/**
	 * Updates relationship on the other side.
	 * @param  IEntity|null $oldEntity
	 * @param  IEntity|null $newEntity
	 * @return void
	 */
	abstract protected function updateRelationship($oldEntity, $newEntity, bool $allowNull);


	/**
	 * @param  IEntity|null $currentEntity
	 * @return mixed
	 */
	abstract protected function initReverseRelationship($currentEntity);
}
