<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;


use Nette\SmartObject;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\NullValueException;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;
use function assert;


abstract class HasOne implements IRelationshipContainer
{
	use SmartObject;


	/** @var IEntity */
	protected $parent;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var PropertyRelationshipMetadata */
	protected $metadataRelationship;

	/**
	 * @var ICollection
	 * @phpstan-var ICollection<IEntity>
	 */
	protected $collection;

	/** @var bool */
	protected $isValueValidated = true;

	/** @var bool */
	protected $isValueFromStorage = false;

	/** @var IEntity|string|int|null */
	protected $value;

	/**
	 * @var IRepository|null
	 * @phpstan-var IRepository<IEntity>|null
	 */
	protected $targetRepository;

	/** @var bool */
	protected $updatingReverseRelationship = false;

	/** @var bool */
	protected $isModified = false;

	/** @var IRelationshipMapper */
	protected $relationshipMapper;


	public function __construct(PropertyMetadata $metadata)
	{
		assert($metadata->relationship !== null);
		$this->metadata = $metadata;
		$this->metadataRelationship = $metadata->relationship;
	}


	public function onEntityAttach(IEntity $entity): void
	{
		$this->parent = $entity;
	}


	public function onEntityRepositoryAttach(IEntity $entity): void
	{
		if (!$this->isValueValidated) {
			$this->getEntity();
			if ($this->value instanceof IEntity) {
				$this->attachIfPossible($this->value);
			}
		}
	}


	public function convertToRawValue($value)
	{
		if ($value instanceof IEntity) {
			return $value->getValue('id');
		}
		return $value;
	}


	public function setRawValue($value): void
	{
		$isChanged = $this->getPrimaryValue() !== $value;
		$this->value = $value;
		$this->isValueValidated = !$isChanged && $value === null;
		$this->isValueFromStorage = true;
	}


	public function getRawValue()
	{
		return $this->getPrimaryValue();
	}


	public function setInjectedValue($value): bool
	{
		return $this->set($value);
	}


	public function &getInjectedValue()
	{
		$value = $this->getEntity();
		return $value;
	}


	public function hasInjectedValue(): bool
	{
		return $this->value !== null;
	}


	public function isLoaded(): bool
	{
		return !$this->isValueFromStorage || $this->isValueValidated;
	}


	/**
	 * Sets the relationship value to passed entity.
	 * Returns true if the setter has modified property value.
	 * @param IEntity|int|string|null $value Accepts also a primary key value.
	 */
	public function set($value, bool $allowNull = false): bool
	{
		if ($this->updatingReverseRelationship) {
			return false;
		}

		if (($this->parent !== null && $this->parent->isAttached()) || $value === null) {
			$entity = $this->createEntity($value, $allowNull);
			$isValueValidated = true;
		} else {
			$entity = $value;
			$isValueValidated = false;
		}

		if ($entity instanceof IEntity || $entity === null) {
			$isChanged = $this->isChanged($entity);
			if ($isChanged) {
				$this->modify();
				$this->updateRelationship($this->getValue(false), $entity, $allowNull);
			} else {
				$this->initReverseRelationship($entity);
			}
		} else {
			$this->modify();
			$isChanged = true;
		}

		$this->value = $entity;
		$this->isValueValidated = $isValueValidated;
		$this->isValueFromStorage = false;
		return $isChanged;
	}


	public function getEntity(): ?IEntity
	{
		$value = $this->getValue();

		if ($value === null && !$this->metadata->isNullable) {
			throw new NullValueException($this->parent, $this->metadata);
		}

		return $value;
	}


	public function isModified(): bool
	{
		return $this->isModified;
	}


	/**
	 * @return mixed|null
	 */
	protected function getPrimaryValue()
	{
		if ($this->value instanceof IEntity) {
			if ($this->value->hasValue('id')) {
				return $this->value->getValue('id');
			} else {
				return null;
			}
		} else {
			return $this->value;
		}
	}


	protected function getValue(bool $allowPreloadContainer = true): ?IEntity
	{
		if (!$this->isValueValidated && $this->value !== null) {
			$this->initValue($allowPreloadContainer);
		}

		assert($this->value instanceof IEntity || $this->value === null);
		return $this->value;
	}


	protected function initValue(bool $allowPreloadContainer = true): void
	{
		if ($this->parent === null) {
			throw new InvalidStateException('Relationship is not attached to a parent entity.');
		}

		if ($this->isValueFromStorage && $allowPreloadContainer) {
			// load the value using relationship mapper to utilize preload container and not to validate if
			// relationship's entity is really present in the database;
			$this->set($this->fetchValue());

		} else {
			$this->set($this->value);
		}
	}


	protected function fetchValue(): ?IEntity
	{
		$collection = $this->getCollection();
		return iterator_to_array($collection->getIterator())[0] ?? null;
	}


	/**
	 * @phpstan-return IRepository<IEntity>
	 */
	protected function getTargetRepository(): IRepository
	{
		if ($this->targetRepository === null) {
			$this->targetRepository = $this->parent->getRepository()->getModel()
				->getRepository($this->metadataRelationship->repository);
		}

		return $this->targetRepository;
	}


	/**
	 * @phpstan-return ICollection<IEntity>
	 */
	protected function getCollection(): ICollection
	{
		if ($this->collection !== null) {
			return $this->collection;
		}

		return $this->collection = $this->createCollection();
	}


	/**
	 * @param IEntity|string|int|null $entity
	 */
	protected function createEntity($entity, bool $allowNull): ?IEntity
	{
		if ($entity instanceof IEntity) {
			$this->attachIfPossible($entity);
			return $entity;

		} elseif ($entity === null) {
			if (!$this->metadata->isNullable && !$allowNull) {
				throw new NullValueException($this->parent, $this->metadata);
			}
			return null;

		} elseif (is_scalar($entity)) {
			return $this->getTargetRepository()->getByIdChecked($entity);

		} else {
			throw new InvalidArgumentException('Value is not a valid entity representation.');
		}
	}


	protected function attachIfPossible(IEntity $entity): void
	{
		if ($this->parent === null) return;

		if ($this->parent->isAttached() && !$entity->isAttached()) {
			$model = $this->parent->getRepository()->getModel();
			$repository = $model->getRepository($this->metadataRelationship->repository);
			$repository->attach($entity);

		} elseif ($entity->isAttached() && !$this->parent->isAttached()) {
			$model = $entity->getRepository()->getModel();
			$repository = $model->getRepositoryForEntity($this->parent);
			$repository->attach($this->parent);
		}
	}


	protected function isChanged(?IEntity $newValue): bool
	{
		if ($this->value instanceof IEntity && $newValue instanceof IEntity) {
			return $this->value !== $newValue;

		} elseif ($this->value instanceof IEntity) {
			// value is an entity
			// newValue is null
			return true;

		} elseif ($newValue instanceof IEntity && $newValue->isPersisted()) {
			// value is persisted entity or null
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
	 * @phpstan-return ICollection<IEntity>
	 */
	abstract protected function createCollection(): ICollection;


	/**
	 * Sets relationship (and entity) as modified.
	 */
	abstract protected function modify(): void;


	/**
	 * Updates relationship on the other side.
	 */
	abstract protected function updateRelationship(?IEntity $oldEntity, ?IEntity $newEntity, bool $allowNull): void;


	abstract protected function initReverseRelationship(?IEntity $currentEntity): void;
}
