<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;


abstract class AbstractEntity implements IEntity
{
	/** @var EntityMetadata */
	protected $metadata;

	/** @var IRepository */
	private $repository;

	/** @var array */
	private $data = [];

	/** @var array */
	private $validated = [];

	/** @var array */
	private $modified = [];

	/** @var mixed */
	private $persistedId = NULL;


	public function __construct()
	{
		$this->fireEvent('onCreate');
		$this->modified[NULL] = TRUE;
		$this->metadata = $this->createMetadata();
	}


	public function fireEvent($method, $args = [])
	{
		call_user_func_array([$this, $method], $args);
	}


	public function getModel($need = TRUE)
	{
		$repository = $this->getRepository($need);
		return $repository ? $repository->getModel($need) : NULL;
	}


	public function getRepository($need = TRUE)
	{
		if ($this->repository === NULL && $need) {
			throw new InvalidStateException('Entity is not attached to repository.');
		}
		return $this->repository;
	}


	public function isAttached()
	{
		return $this->repository !== NULL;
	}


	public function getMetadata()
	{
		return $this->metadata;
	}


	public function isModified($name = NULL)
	{
		if ($name === NULL) {
			return (bool) $this->modified;
		}

		$this->metadata->getProperty($name); // checks property existence
		return isset($this->modified[NULL]) || isset($this->modified[$name]);
	}


	public function setAsModified($name = NULL)
	{
		$this->modified[$name] = TRUE;
		return $this;
	}


	public function isPersisted()
	{
		return $this->persistedId !== NULL;
	}


	public function getPersistedId()
	{
		return $this->persistedId;
	}


	public function setValue($name, $value)
	{
		$metadata = $this->metadata->getProperty($name);
		if ($metadata->isReadonly) {
			throw new InvalidArgumentException("Property '$name' is read-only.");
		}

		$this->internalSetValue($metadata, $name, $value);
		return $this;
	}


	public function setReadOnlyValue($name, $value)
	{
		$metadata = $this->metadata->getProperty($name);
		$this->internalSetValue($metadata, $name, $value);
		return $this;
	}


	public function & getValue($name)
	{
		$property = $this->metadata->getProperty($name);
		return $this->internalGetValue($property, $name);
	}


	public function hasValue($name)
	{
		if (!$this->metadata->hasProperty($name)) {
			return FALSE;
		}

		return $this->internalHasValue($this->metadata->getProperty($name), $name);
	}


	public function setRawValue($name, $value)
	{
		$this->metadata->getProperty($name);

		if (isset($this->data[$name]) && $this->data[$name] instanceof IProperty) {
			$this->data[$name]->setRawValue($value);
		} else {
			$this->data[$name] = $value;
			$this->modified[$name] = TRUE;
			$this->validated[$name] = FALSE;
		}
	}


	public function & getRawValue($name)
	{
		$propertyMetadata = $this->metadata->getProperty($name);
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		$value = $this->data[$name];
		if ($value instanceof IProperty) {
			$value = $value->getRawValue();
		}
		return $value;
	}


	public function getProperty($name)
	{
		$propertyMetadata = $this->metadata->getProperty($name);
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		return $this->data[$name];
	}


	public function getRawProperty($name)
	{
		$this->metadata->getProperty($name);
		return isset($this->data[$name]) ? $this->data[$name] : NULL;
	}


	public function toArray($mode = self::TO_ARRAY_RELATIONSHIP_AS_IS)
	{
		return ToArrayConverter::toArray($this, $mode);
	}


	public function __clone()
	{
		$id = $this->getValue('id');
		foreach ($this->getMetadata()->getProperties() as $name => $metadataProperty) {
			if ($metadataProperty->isVirtual) {
				continue;
			}

			// getValue loads data & checks for not null values
			if ($this->getValue($name) && is_object($this->data[$name])) {
				if ($this->data[$name] instanceof IRelationshipCollection) {
					$data = iterator_to_array($this->data[$name]->get());
					$this->setValue('id', NULL);
					$this->data[$name] = clone $this->data[$name];
					$this->data[$name]->setParent($this);
					$this->data[$name]->set($data);
					$this->setValue('id', $id);

				} elseif ($this->data[$name] instanceof IRelationshipContainer) {
					$this->data[$name] = clone $this->data[$name];
					$this->data[$name]->setParent($this);

				} else {
					$this->data[$name] = clone $this->data[$name];
				}
			}
		}
		$this->setValue('id', NULL);
		$this->persistedId = NULL;

		if ($repository = $this->repository) {
			$this->repository = NULL;
			$repository->attach($this);
		}
	}


	public function serialize()
	{
		return [
			'modified' => $this->modified,
			'validated' => $this->validated,
			'data' => $this->toArray(IEntity::TO_ARRAY_RELATIONSHIP_AS_ID),
			'persistedId' => $this->persistedId,
		];
	}


	public function unserialize($unserialized)
	{
		$this->persistedId = $unserialized['persistedId'];
		$this->modified = $unserialized['modified'];
		$this->validated = $unserialized['validated'];
		$this->data = $unserialized['data'];
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

		$this->persistedId = $this->getterId();
	}


	protected function onFree()
	{
		$this->data = [];
		$this->persistedId = NULL;
		$this->validated = [];
	}


	protected function onAttach(IRepository $repository, EntityMetadata $metadata)
	{
		$this->attach($repository);
		$this->metadata = $metadata;
	}


	protected function onDetach()
	{
		$this->repository = NULL;
	}


	protected function onPersist($id)
	{
		$this->setterId($id);
		$this->persistedId = $this->getterId();
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
		$this->repository = NULL;
		$this->persistedId = NULL;
		$this->modified = [];
	}


	// === internal implementation =====================================================================================


	protected function createMetadata()
	{
		return MetadataStorage::get(get_class($this));
	}


	protected function setterId($id)
	{
		$id = is_array($id) ? $id : [$id]; // casting null to array produces empty array
		$keys = $this->metadata->getPrimaryKey();
		if (count($keys) !== count($id)) {
			throw new InvalidArgumentException('Insufficient parameters for primary value.');
		}

		foreach ($keys as $key) {
			$this->setRawValue($key, array_shift($id));
		}

		return IEntity::SKIP_SET_VALUE;
	}


	protected function getterId()
	{
		$keys = $this->metadata->getPrimaryKey();
		if (count($keys) === 1) {
			return $this->getRawValue($keys[0]);
		} else {
			$primary = [];
			foreach ($keys as $key) {
				$primary[] = $this->getRawValue($key);
			}
			return $primary;
		}
	}


	private function internalSetValue(PropertyMetadata $metadata, $name, $value)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			$this->data[$name]->setInjectedValue($value);
			return;
		}

		if ($metadata->hasSetter) {
			$value = call_user_func([$this, 'setter' . $name], $value);
			if ($value === IEntity::SKIP_SET_VALUE) {
				$value = $this->data[$name];
			}
		}
		if (!$metadata->isValid($value)) {
			$class = get_class($this);
			throw new InvalidArgumentException("Value for {$class}::\${$name} property is invalid.");
		}
		$this->data[$name] = $value;
		$this->modified[$name] = TRUE;
	}


	private function & internalGetValue(PropertyMetadata $propertyMetadata, $name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->getInjectedValue();
		}

		if ($propertyMetadata->hasGetter) {
			if (!$propertyMetadata->isVirtual) {
				$value = call_user_func([$this, 'getter' . $name], $this->data[$name]);
			} else {
				$value = call_user_func([$this, 'getter' . $name]);
			}
		} else {
			$value = $this->data[$name];
		}
		if (!isset($value) && !$propertyMetadata->isNullable) {
			$class = get_class($this);
			throw new InvalidStateException("Property {$class}::\${$name} is not set.");
		}
		return $value;
	}


	private function internalHasValue(PropertyMetadata $propertyMetadata, $name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->hasInjectedValue();

		} elseif ($propertyMetadata->hasGetter) {
			if (!$propertyMetadata->isVirtual) {
				$value = call_user_func([$this, 'getter' . $name], $this->data[$name]);
			} else {
				$value = call_user_func([$this, 'getter' . $name]);
			}
			return isset($value);

		} else {
			return isset($this->data[$name]);
		}
	}


	private function initProperty(PropertyMetadata $propertyMetadata, $name)
	{
		$this->validated[$name] = TRUE;

		if (!isset($this->data[$name]) && !array_key_exists($name, $this->data)) {
			$this->data[$name] = $this->persistedId === NULL ? $propertyMetadata->defaultValue : NULL;
		}

		if ($propertyMetadata->container) {
			$class = $propertyMetadata->container;

			/** @var IProperty $property */
			$property = new $class($this, $propertyMetadata);
			$property->setRawValue($this->data[$name]);
			$this->data[$name] = $property;

		} elseif ($this->data[$name] !== NULL) {
			$this->internalSetValue($propertyMetadata, $name, $this->data[$name]);
			unset($this->modified[$name]);
		}
	}


	private function attach(IRepository $repository)
	{
		if ($this->repository !== NULL && $this->repository !== $repository) {
			throw new InvalidStateException('Entity is already attached.');
		}

		$this->repository = $repository;
	}

}
