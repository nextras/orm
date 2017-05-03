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
use Nextras\Orm\Relationships\HasMany;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\OneHasOne;
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
		$this->fireEvent('onCreate');
	}


	public function fireEvent(string $method, array $args = [])
	{
		call_user_func_array([$this, $method], $args);
	}


	public function getRepository(bool $need = true)
	{
		if ($this->repository === null && $need) {
			throw new InvalidStateException('Entity is not attached to repository.');
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


	public function setAsModified(string $name = null): self
	{
		$this->modified[$name] = true;
		return $this;
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
		if (!$this->metadata->hasProperty($name)) {
			return false;
		}

		return $this->internalHasValue($this->metadata->getProperty($name), $name);
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
		return isset($this->data[$name]) ? $this->data[$name] : null;
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
					$this->data[$name]->setParent($this);
					$this->data[$name]->set($data);
					$this->data['id'] = $id;
					$this->persistedId = $persistedId;

				} elseif ($this->data[$name] instanceof IRelationshipContainer) {
					$this->data[$name] = clone $this->data[$name];
					$this->data[$name]->setParent($this);

				} else {
					$this->data[$name] = clone $this->data[$name];
				}
			}
		}
		$this->data['id'] = null;
		$this->persistedId = null;
		$this->modified[null] = true;

		if ($repository = $this->repository) {
			$this->repository = null;
			$repository->attach($this);
		}
	}


	// === events ======================================================================================================


	protected function onCreate()
	{
	}


	protected function onLoad(array $data)
	{
		foreach ($this->metadata->getProperties() as $name => $metadataProperty) {
			if (!$metadataProperty->isVirtual && isset($data[$name])) {
				$this->data[$name] = $data[$name];
			}
		}

		$this->persistedId = $this->getValue('id');
	}


	protected function onRefresh(array $data)
	{
		foreach ($this->metadata->getProperties() as $name => $metadataProperty) {
			if (isset($this->data[$name]) && $this->data[$name] instanceof HasMany) {
				$this->data[$name]->clean();
			}
			if (!$metadataProperty->isVirtual && isset($data[$name])) {
				if (isset($this->data[$name]) && $this->data[$name] instanceof OneHasOne) {
					$this->data[$name]->set(null, true);
				}
				$this->internalSetValue($metadataProperty, $name, $data[$name]);
			}
			unset($this->modified[$name]);
		}
	}


	protected function onFree()
	{
		$this->data = [];
		$this->persistedId = null;
		$this->validated = [];
	}


	protected function onAttach(IRepository $repository, EntityMetadata $metadata)
	{
		$this->attach($repository);
		$this->metadata = $metadata;
	}


	protected function onDetach()
	{
		$this->repository = null;
	}


	protected function onPersist($id)
	{
		// $id property may be marked as read-only
		$this->setReadOnlyValue('id', $id);
		$this->persistedId = $this->getValue('id');
		$this->modified = [];
	}


	protected function onBeforePersist()
	{
	}


	protected function onAfterPersist()
	{
	}


	protected function onBeforeInsert()
	{
	}


	protected function onAfterInsert()
	{
	}


	protected function onBeforeUpdate()
	{
	}


	protected function onAfterUpdate()
	{
	}


	protected function onBeforeRemove()
	{
	}


	protected function onAfterRemove()
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

		if (count($keys) !== count($value)) {
			$class = get_class($this);
			throw new InvalidStateException("Value for $class::\$id has insufficient number of parameters.");
		}

		$value = (array) $value;
		foreach ($keys as $key) {
			$this->setRawValue($key, array_shift($value));
		}
		return IEntity::SKIP_SET_VALUE;
	}


	private function getterPrimaryProxy($value = null, PropertyMetadata $metadata)
	{
		if ($this->persistedId !== null) {
			return $this->persistedId;
		} elseif (!$metadata->isVirtual) {
			return $value;
		}

		$id = [];
		$keys = $this->getMetadata()->getPrimaryKey();
		foreach ($keys as $key) {
			$id[] = $value = $this->getRawValue($key);
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
			$this->data[$name]->setInjectedValue($value);
			return;
		}

		if ($metadata->hasSetter) {
			$value = call_user_func([$this, $metadata->hasSetter], $value, $metadata);
			if ($value === IEntity::SKIP_SET_VALUE) {
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
			return $this->data[$name]->getInjectedValue();
		}

		if ($metadata->hasGetter) {
			$value = call_user_func(
				[$this, $metadata->hasGetter],
				$metadata->isVirtual ? null : $this->data[$name],
				$metadata
			);
		} else {
			$value = $this->data[$name];
		}
		if (!isset($value) && !$metadata->isNullable) {
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
			return $this->data[$name]->hasInjectedValue();

		} elseif ($metadata->hasGetter) {
			$value = call_user_func(
				[$this, $metadata->hasGetter],
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
		return new $class($this, $metadata);
	}


	private function initProperty(PropertyMetadata $metadata, string $name)
	{
		$this->validated[$name] = true;

		if (!isset($this->data[$name]) && !array_key_exists($name, $this->data)) {
			$this->data[$name] = $this->persistedId === null ? $metadata->defaultValue : null;
		}

		if ($metadata->container) {
			$property = $this->createPropertyContainer($metadata);
			$property->setRawValue($this->data[$name]);
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
