<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Fragments;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\ToArrayConverter;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;


abstract class DataEntityFragment extends RepositoryEntityFragment implements IEntity
{
	/** @var EntityMetadata */
	protected $metadata;

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
		parent::__construct();
		$this->modified[NULL] = TRUE;
		$this->metadata = $this->createMetadata();
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

		return $this->internalHasValue($name);
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
		parent::__clone();
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


	public function __debugInfo()
	{
		return $this->data;
	}


	// === events ======================================================================================================


	protected function onLoad(array $data)
	{
		parent::onLoad($data);
		foreach ($this->metadata->getProperties() as $name => $metadataProperty) {
			if (!$metadataProperty->isVirtual && isset($data[$name])) {
				$this->data[$name] = $data[$name];
			}
		}

		$this->persistedId = $this->getterId();
	}


	protected function onFree()
	{
		parent::onFree();
		$this->data = [];
		$this->persistedId = NULL;
		$this->validated = [];
	}


	protected function onAttach(IRepository $repository, EntityMetadata $metadata)
	{
		parent::onAttach($repository, $metadata);
		$this->metadata = $metadata;
	}


	protected function onPersist($id)
	{
		parent::onPersist($id);
		$this->setterId($id);
		$this->persistedId = $this->getterId();
		$this->modified = [];
	}


	protected function onAfterRemove()
	{
		parent::onAfterRemove();
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


	private function internalHasValue($name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($this->metadata->getProperty($name), $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->hasInjectedValue();

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

}
