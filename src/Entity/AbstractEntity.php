<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Repository\IRepository;


abstract class AbstractEntity implements IEntity
{
	/** @var EntityMetadata */
	protected $metadata;

	/** @var IRepository|null */
	private $repository;

	/** @var array */
	private $data = [];

	/** @var array */
	private $validated = [];

	/** @var array */
	private $modified = [];

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


	public function isModified(string $name = null): bool
	{
		if ($name === null) {
			return (bool) $this->modified;
		}

		$this->metadata->getProperty($name); // checks property existence
		return isset($this->modified[null]) || isset($this->modified[$name]);
	}


	public function setAsModified(string $name = null)
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


	public function &getValue(string $name)
	{
		$property = $this->metadata->getProperty($name);
		return $this->internalGetValue($property, $name);
	}


	public function hasValue(string $name): bool
	{
		$property = $this->metadata->getProperty($name);
		return $this->internalHasValue($property, $name);
	}


	public function setRawValue(string $name, $value)
	{
		$property = $this->metadata->getProperty($name);
		if ($property->isVirtual) {
			$this->internalSetValue($property, $name, $value);
			return;
		}

		if (isset($this->data[$name]) && $this->data[$name] instanceof IProperty) {
			$this->data[$name]->setRawValue($value);
		} else {
			$this->data[$name] = $value;
			$this->modified[$name] = true;
			$this->validated[$name] = false;
		}
	}


	public function &getRawValue(string $name)
	{
		$property = $this->metadata->getProperty($name);
		if ($property->isVirtual) {
			$value = $this->internalGetValue($property, $name);
			return $value;
		}

		if (!isset($this->validated[$name])) {
			$this->initProperty($property, $name);
		}

		$value = $this->data[$name];
		if ($value instanceof IProperty) {
			$value = $value->getRawValue();
		}
		return $value;
	}


	public function getProperty(string $name)
	{
		$propertyMetadata = $this->metadata->getProperty($name);
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		return $this->data[$name];
	}


	public function getRawProperty(string $name)
	{
		$this->metadata->getProperty($name);
		return $this->data[$name] ?? null;
	}


	public function toArray(int $mode = ToArrayConverter::RELATIONSHIP_AS_IS): array
	{
		return ToArrayConverter::toArray($this, $mode);
	}


	public function __clone()
	{
		$id = $this->hasValue('id') ? $this->getValue('id') : null;
		$persistedId = $this->persistedId;
		foreach ($this->getMetadata()->getProperties() as $name => $metadataProperty) {
			if ($metadataProperty->isVirtual) {
				continue;
			}

			// getValue loads data & checks for not null values
			if ($this->hasValue($name) && is_object($this->data[$name])) {
				if ($this->data[$name] instanceof IRelationshipCollection) {
					$data = iterator_to_array($this->data[$name]->get());
					$this->data['id'] = null;
					$this->persistedId = null;
					$this->data[$name] = clone $this->data[$name];
					$this->data[$name]->setPropertyEntity($this);
					$this->data[$name]->set($data);
					$this->data['id'] = $id;
					$this->persistedId = $persistedId;

				} elseif ($this->data[$name] instanceof IRelationshipContainer) {
					$this->data[$name] = clone $this->data[$name];
					$this->data[$name]->setPropertyEntity($this);

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


	public function onCreate()
	{
	}


	public function onLoad(array $data)
	{
		foreach ($this->metadata->getProperties() as $name => $metadataProperty) {
			if (!$metadataProperty->isVirtual && isset($data[$name])) {
				$this->data[$name] = $data[$name];
			}
		}

		$this->persistedId = $this->getValue('id');
	}


	public function onRefresh(?array $data, bool $isPartial = false)
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


	public function onFree()
	{
		$this->data = [];
		$this->persistedId = null;
		$this->validated = [];
	}


	public function onAttach(IRepository $repository, EntityMetadata $metadata)
	{
		$this->attach($repository);
		$this->metadata = $metadata;
	}


	public function onDetach()
	{
		$this->repository = null;
	}


	public function onPersist($id)
	{
		// $id property may be marked as read-only
		$this->setReadOnlyValue('id', $id);
		$this->persistedId = $this->getValue('id');
		$this->modified = [];
	}


	public function onBeforePersist()
	{
	}


	public function onAfterPersist()
	{
	}


	public function onBeforeInsert()
	{
	}


	public function onAfterInsert()
	{
	}


	public function onBeforeUpdate()
	{
	}


	public function onAfterUpdate()
	{
	}


	public function onBeforeRemove()
	{
	}


	public function onAfterRemove()
	{
		$this->repository = null;
		$this->persistedId = null;
		$this->modified = [];
	}


	// === internal implementation =====================================================================================


	protected function createMetadata(): EntityMetadata
	{
		return MetadataStorage::get(get_class($this));
	}


	private function setterPrimaryProxy($value, PropertyMetadata $metadata)
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


	private function getterPrimaryProxy($value, PropertyMetadata $metadata)
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


	private function internalSetValue(PropertyMetadata $metadata, string $name, $value)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyInjection) {
			$this->data[$name]->setInjectedValue($this, $value);
			return;
		}

		if ($metadata->hasSetter) {
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


	private function &internalGetValue(PropertyMetadata $metadata, string $name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->getInjectedValue($this);
		}

		if ($metadata->hasGetter) {
			/** @var callable $cb */
			$cb = [$this, $metadata->hasGetter];
			$value = call_user_func(
				$cb,
				$metadata->isVirtual ? null : $this->data[$name],
				$metadata
			);
		} else {
			$value = $this->data[$name];
		}
		if ($value === null && !$metadata->isNullable) {
			$class = get_class($this);
			throw new InvalidStateException("Property {$class}::\${$name} is not set.");
		}
		return $value;
	}


	private function internalHasValue(PropertyMetadata $metadata, string $name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->hasInjectedValue($this);

		} elseif ($metadata->hasGetter) {
			/** @var callable $cb */
			$cb = [$this, $metadata->hasGetter];
			$value = call_user_func(
				$cb,
				$metadata->isVirtual ? null : $this->data[$name],
				$metadata
			);
			return isset($value);

		} else {
			return isset($this->data[$name]);
		}
	}


	/**
	 * Validates the value.
	 * @param  mixed $value
	 * @throws InvalidArgumentException
	 */
	protected function validate(PropertyMetadata $metadata, string $name, & $value)
	{
		if (!$metadata->isValid($value)) {
			$class = get_class($this);
			throw new InvalidArgumentException("Value for {$class}::\${$name} property is invalid.");
		}
	}


	protected function createPropertyContainer(PropertyMetadata $metadata): IProperty
	{
		$class = $metadata->container;
		$container = new $class($metadata);
		assert($container instanceof IProperty);
		if ($container instanceof IEntityAwareProperty) {
			$container->setPropertyEntity($this);
		}
		return $container;
	}


	private function initProperty(PropertyMetadata $metadata, string $name)
	{
		$this->validated[$name] = true;

		if (!isset($this->data[$name]) && !array_key_exists($name, $this->data)) {
			$this->data[$name] = $this->persistedId === null ? $metadata->defaultValue : null;
		}

		if ($metadata->container) {
			$property = $this->createPropertyContainer($metadata);
			$property->loadValue($this, $this->data);
			$this->data[$name] = $property;

		} elseif ($this->data[$name] !== null) {
			$this->internalSetValue($metadata, $name, $this->data[$name]);
			unset($this->modified[$name]);
		}
	}


	private function attach(IRepository $repository)
	{
		if ($this->repository !== null && $this->repository !== $repository) {
			throw new InvalidStateException('Entity is already attached.');
		}

		$this->repository = $repository;
	}
}
