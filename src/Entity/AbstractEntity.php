<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\LogicException;
use Nextras\Orm\Exception\NullValueException;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Repository\IRepository;
use function assert;
use function get_class;


abstract class AbstractEntity implements IEntity
{
	use ImmutableDataTrait;


	/** @var IRepository<IEntity>|null */
	private IRepository|null $repository = null;

	/** @var array<string, bool> */
	private array $modified = [];

	/** @var mixed */
	private $persistedId = null;


	public function __construct()
	{
		$this->modified[null] = true;
		$this->metadata = $this->createMetadata();
		$this->onCreate();
	}


	public function getRepository(): IRepository
	{
		if ($this->repository === null) {
			throw new InvalidStateException('Entity is not attached to a repository. Use IEntity::isAttached() method to check the state.');
		}
		return $this->repository;
	}


	public function isAttached(): bool
	{
		return $this->repository !== null;
	}


	public function getMetadata(): EntityMetadata
	{
		return $this->metadata;
	}


	public function isModified(string|null $name = null): bool
	{
		if ($name === null) {
			return (bool) $this->modified;
		}

		$this->metadata->getProperty($name); // checks property existence
		return isset($this->modified[null]) || isset($this->modified[$name]);
	}


	public function setAsModified(string|null $name = null): void
	{
		$this->modified[$name] = true;
	}


	public function isPersisted(): bool
	{
		return $this->persistedId !== null;
	}


	public function getPersistedId()
	{
		return $this->persistedId;
	}


	public function setValue(string $name, $value)
	{
		$metadata = $this->metadata->getProperty($name);
		if ($metadata->isReadonly) {
			throw new InvalidArgumentException("Property '$name' is read-only.");
		}

		$this->internalSetValue($metadata, $name, $value);
		return $this;
	}


	public function setReadOnlyValue(string $name, $value)
	{
		$metadata = $this->metadata->getProperty($name);
		$this->internalSetValue($metadata, $name, $value);
		return $this;
	}


	public function setRawValue(string $name, $value): void
	{
		$property = $this->metadata->getProperty($name);

		if ($property->wrapper !== null) {
			if ($this->data[$name] instanceof IProperty) {
				$this->data[$name]->setRawValue($value);
				return;
			}
		} elseif ($property->isVirtual) {
			$this->internalSetValue($property, $name, $value);
			return;
		}

		$this->data[$name] = $value;
		$this->modified[$name] = true;
		$this->validated[$name] = false;
	}


	public function &getRawValue(string $name)
	{
		$property = $this->metadata->getProperty($name);

		if (!isset($this->validated[$name])) {
			$this->initProperty($property, $name);
		}

		$value = $this->data[$name];

		if ($value instanceof IProperty) {
			$value = $value->getRawValue();
			return $value;
		}

		if ($property->isVirtual) {
			$value = $this->internalGetValue($property, $name);
			return $value;
		}

		return $value;
	}


	public function getProperty(string $name): IProperty
	{
		$propertyMetadata = $this->metadata->getProperty($name);
		if ($propertyMetadata->wrapper === null) {
			$class = get_class($this);
			throw new InvalidStateException("Property $class::\$$name does not have a property wrapper.");
		}
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		return $this->data[$name];
	}


	public function getRawProperty(string $name)
	{
		$propertyMetadata = $this->metadata->getProperty($name);
		if ($propertyMetadata->wrapper === null) {
			$class = get_class($this);
			throw new InvalidStateException("Property $class::\$$name does not have a property wrapper.");
		}
		return $this->data[$name] ?? null;
	}


	public function getRawValues(bool $modifiedOnly = false): array
	{
		$out = [];
		$exportModified = $modifiedOnly && $this->isPersisted();

		foreach ($this->metadata->getProperties() as $name => $propertyMetadata) {
			if ($propertyMetadata->isVirtual) continue;
			if ($propertyMetadata->isPrimary && !$this->hasValue($name)) continue;
			if ($exportModified && !$this->isModified($name)) continue;

			if ($propertyMetadata->wrapper === null) {
				if (!isset($this->validated[$name])) {
					$this->initProperty($propertyMetadata, $name);
				}
				$out[$name] = $this->data[$name];

			} else {
				$out[$name] = $this->getProperty($name)->getRawValue();
				if ($out[$name] === null && !$propertyMetadata->isNullable) {
					throw new NullValueException($propertyMetadata);
				}
			}
		}

		return $out;
	}


	/**
	 * @return array<string, mixed>
	 */
	public function toArray(int $mode = ToArrayConverter::RELATIONSHIP_AS_IS): array
	{
		return ToArrayConverter::toArray($this, $mode);
	}


	public function __clone()
	{
		$id = $this->hasValue('id') ? $this->getValue('id') : null;
		$persistedId = $this->persistedId;
		$isAttached = $this->isAttached();
		foreach ($this->getMetadata()->getProperties() as $name => $metadataProperty) {
			// getValue loads data & checks for not null values
			if ($this->hasValue($name) && is_object($this->data[$name])) {
				if ($this->data[$name] instanceof IRelationshipCollection) {
					$data = iterator_to_array($this->data[$name]->toCollection());
					$this->data['id'] = null;
					$this->persistedId = null;
					$this->data[$name] = clone $this->data[$name];
					$this->data[$name]->onEntityAttach($this);
					if ($isAttached) {
						$this->data[$name]->onEntityRepositoryAttach($this);
					}
					$this->data[$name]->set($data);
					$this->data['id'] = $id;
					$this->persistedId = $persistedId;

				} elseif ($this->data[$name] instanceof IRelationshipContainer) {
					$this->data[$name] = clone $this->data[$name];
					$this->data[$name]->onEntityAttach($this);
					if ($isAttached) {
						$this->data[$name]->onEntityRepositoryAttach($this);
					}

				} elseif ($this->data[$name] instanceof EmbeddableContainer) {
					$this->data[$name] = clone $this->data[$name];
					$this->data[$name]->onEntityAttach($this);
					if ($isAttached) {
						$this->data[$name]->onEntityRepositoryAttach($this);
					}

				} else {
					$this->data[$name] = clone $this->data[$name];
				}
			}
		}
		$this->data['id'] = null;
		$this->persistedId = null;
		$this->modified[null] = true;

		if ($this->repository !== null) {
			$repository = $this->repository;
			$this->repository = null;
			$repository->attach($this);
		}
	}


	// === events ======================================================================================================

	public function onCreate(): void
	{
	}


	public function onLoad(array $data): void
	{
		foreach ($this->metadata->getProperties() as $name => $metadataProperty) {
			if ($metadataProperty->isVirtual) continue;

			if (isset($data[$name])) {
				$this->data[$name] = $data[$name];
			}
		}

		$this->persistedId = $this->getValue('id');
	}


	public function onRefresh(?array $data, bool $isPartial = false): void
	{
		if ($data === null) {
			throw new InvalidStateException('Refetching data failed. Entity is not present in storage anymore.');
		}
		if ($isPartial) {
			foreach ($data as $name => $value) {
				$this->data[$name] = $value;
				unset($this->modified[$name], $this->validated[$name]);
			}

		} else {
			$this->data = $data;
			$this->validated = [];
			$this->modified = [];
		}
	}


	public function onFree(): void
	{
		$this->data = [];
		$this->persistedId = null;
		$this->validated = [];
	}


	public function onAttach(IRepository $repository, EntityMetadata $metadata): void
	{
		if ($this->isAttached()) {
			return;
		}

		$this->repository = $repository;
		$this->metadata = $metadata;

		foreach ($this->data as $property) {
			if ($property instanceof IEntityAwareProperty) {
				$property->onEntityRepositoryAttach($this);
			}
		}
	}


	public function onDetach(): void
	{
		$this->repository = null;
	}


	public function onPersist($id): void
	{
		// $id property may be marked as read-only @phpstan-ignore-next-line
		$this->setReadOnlyValue('id', $id);
		$this->persistedId = $this->getValue('id');
		$this->modified = [];
	}


	public function onBeforePersist(): void
	{
	}


	public function onAfterPersist(): void
	{
	}


	public function onBeforeInsert(): void
	{
	}


	public function onAfterInsert(): void
	{
	}


	public function onBeforeUpdate(): void
	{
	}


	public function onAfterUpdate(): void
	{
	}


	public function onBeforeRemove(): void
	{
	}


	public function onAfterRemove(): void
	{
		$this->repository = null;
		$this->persistedId = null;
		$this->modified = [];
	}


	// === internal implementation =====================================================================================


	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function setterPrimaryProxy($value, PropertyMetadata $metadata) // @phpstan-ignore-line
	{
		$keys = $this->metadata->getPrimaryKey();
		if (!$metadata->isVirtual) {
			return $value;
		}

		if (count($keys) === 1) {
			$value = [$value];
		} elseif (!is_array($value)) {
			$class = get_class($this);
			throw new InvalidArgumentException("Value for $class::\$id has to be passed as array.");
		}

		if (count($keys) !== count($value)) {
			$class = get_class($this);
			throw new InvalidArgumentException("Value for $class::\$id has insufficient number of parameters.");
		}

		foreach ($keys as $key) {
			$this->setRawValue($key, array_shift($value));
		}
		return null;
	}


	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function getterPrimaryProxy($value, PropertyMetadata $metadata) // @phpstan-ignore-line
	{
		if ($this->persistedId !== null) {
			return $this->persistedId;
		} elseif (!$metadata->isVirtual) {
			return $value;
		}

		$id = [];
		$keys = $this->getMetadata()->getPrimaryKey();
		foreach ($keys as $key) {
			$id[] = $this->getRawValue($key);
		}
		if (count($keys) === 1) {
			return $id[0];
		} else {
			return $id;
		}
	}


	/**
	 * @param mixed $value
	 */
	private function internalSetValue(PropertyMetadata $metadata, string $name, $value): void
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name, /* $initValue = */ false);
		}

		$property = $this->data[$name];
		if ($property instanceof IPropertyContainer) {
			if ($property->setInjectedValue($value)) {
				$this->setAsModified($name);
			}
			return;
		} elseif ($property instanceof IProperty) {
			$class = get_class($this);
			throw new LogicException("You cannot set property wrapper's value in $class::\$$name directly.");
		}

		if ($metadata->hasSetter !== null) {
			/** @var callable $cb */
			$cb = [$this, $metadata->hasSetter];
			$value = call_user_func($cb, $value, $metadata);
			if ($metadata->isVirtual) {
				$this->modified[$name] = true;
				return;
			}
		}

		$this->validate($metadata, $name, $value);
		$this->data[$name] = $value;
		$this->modified[$name] = true;
	}


	protected function initProperty(PropertyMetadata $metadata, string $name, bool $initValue = true): void
	{
		$this->validated[$name] = true;

		if (!isset($this->data[$name]) && !array_key_exists($name, $this->data)) {
			$this->data[$name] = $this->persistedId === null ? $metadata->defaultValue : null;
		}

		if ($metadata->wrapper !== null) {
			$wrapper = $this->createPropertyWrapper($metadata);
			if ($initValue || isset($this->data[$metadata->name])) {
				$wrapper->setRawValue($this->data[$metadata->name] ?? null);
			}
			$this->data[$name] = $wrapper;
			return;
		}


		if ($this->data[$name] !== null) {
			// data type coercion
			// we validate only when value is not a null to not validate the missing value
			// from db or which has not been set yet
			$this->validate($metadata, $name, $this->data[$name]);
		}
	}


	private function createPropertyWrapper(PropertyMetadata $metadata): IProperty
	{
		$class = $metadata->wrapper;
		$wrapper = new $class($metadata);
		assert($wrapper instanceof IProperty);

		if ($wrapper instanceof IEntityAwareProperty) {
			$wrapper->onEntityAttach($this);
			if ($this->isAttached()) {
				$wrapper->onEntityRepositoryAttach($this);
			}
		}

		return $wrapper;
	}
}
