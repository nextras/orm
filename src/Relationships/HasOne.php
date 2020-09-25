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
	protected $isValueLoaded = true;

	/** @var bool */
	protected $isValueFromStorage = false;

	/** @var mixed|null */
	protected $primaryValue;

	/** @var IEntity|null */
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


	/**
	 * @internal
	 * @ignore
	 */
	public function setPropertyEntity(IEntity $parent): void
	{
		$this->parent = $parent;

		if (!$this->isValueLoaded) {
			// init value
			$this->getEntity();
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
		$isChanged = $this->primaryValue !== $value;
		$this->primaryValue = $value;
		$this->isValueLoaded = !$isChanged && $value === null;
		$this->isValueFromStorage = true;
	}


	public function getRawValue()
	{
		return $this->getPrimaryValue();
	}


	public function setInjectedValue($value): bool
	{
		$this->isValueFromStorage = false;
		return $this->set($value);
	}


	public function &getInjectedValue()
	{
		$value = $this->getEntity();
		return $value;
	}


	public function hasInjectedValue(): bool
	{
		return $this->value instanceof IEntity || $this->getPrimaryValue() !== null;
	}


	public function isLoaded(): bool
	{
		return $this->isValueLoaded;
	}


	/**
	 * Sets the relationship value to passed entity.
	 * Returns true if the setter has modified property value.
	 * @param IEntity|null|int|string $value Accepts also a primary key, if any of the entities is attached to repository.
	 */
	public function set($value, bool $allowNull = false): bool
	{
		if ($this->updatingReverseRelationship) {
			return false;
		}

		if ($this->parent === null) {
			if ($value instanceof IEntity) {
				$this->value = $value;
				$this->primaryValue = $value !== null && $value->hasValue('id') ? $value->getValue('id') : null;
			} else {
				$this->primaryValue = $value;
			}
			$this->isValueLoaded = false;
			return true;
		}

		$entity = $this->createEntity($value, $allowNull);
		$isChanged = $this->isChanged($entity);

		if ($isChanged) {
			$this->modify();
			$oldValue = $this->value;
			if ($oldValue === false) {
				$primaryValue = $this->getPrimaryValue();
				$oldValue = $primaryValue !== null ? $this->getTargetRepository()->getById($primaryValue) : null;
			}
			$this->updateRelationship($oldValue, $entity, $allowNull);

		} else {
			$this->initReverseRelationship($entity);
		}

		$this->isValueLoaded = true;
		$this->primaryValue = $entity !== null && $entity->hasValue('id') ? $entity->getValue('id') : null;
		$this->value = $entity;
		return $isChanged;
	}


	public function getEntity(): ?IEntity
	{
		if (!$this->isValueLoaded && $this->value === null) {
			$this->initValue();
		}

		if ($this->value === null && !$this->metadata->isNullable) {
			throw new NullValueException($this->parent, $this->metadata);
		}

		return $this->value;
	}


	public function isModified(): bool
	{
		return $this->isModified;
	}


	protected function initValue(): void
	{
		if ($this->parent === null) {
			throw new InvalidStateException('Relationship is not attached to a parent entity.');
		}
		if ($this->isValueFromStorage) {
			// load the value using relationship mapper to utilize preload container and not to validate if
			// relationship's entity is really present in the database;
			$this->set($this->fetchValue());
		} else {
			// load value directly to utilize value check
			if ($this->value !== null && $this->value->hasValue('id')) {
				$this->set($this->value->getValue('id'));
			} else {
				$this->set($this->primaryValue);
			}
		}
	}


	protected function fetchValue(): ?IEntity
	{
		$collection = $this->getCollection();
		return iterator_to_array($collection->getIterator())[0] ?? null;
	}


	/**
	 * @return mixed|null
	 */
	protected function getPrimaryValue()
	{
		if ($this->primaryValue === null && $this->value instanceof IEntity && $this->value->hasValue('id')) {
			$this->primaryValue = $this->value->getValue('id');
		}

		return $this->primaryValue;
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
			$result = $this->getTargetRepository()->getById($entity);
			if ($result === null && $entity !== null) {
				throw new InvalidArgumentException("Entity with primary key '$entity' was not found.");
			}
			return $result;

		} else {
			throw new InvalidArgumentException('Value is not a valid entity representation.');
		}
	}


	protected function attachIfPossible(IEntity $entity): void
	{
		if ($this->parent === null) return;

		if ($this->parent->isAttached()) {
			$repository = $this->parent->getRepository()->getModel()
				->getRepository($this->metadataRelationship->repository);
			$repository->attach($entity);

		} elseif ($entity->isAttached()) {
			$repository = $entity->getRepository()->getModel()->getRepositoryForEntity($this->parent);
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
