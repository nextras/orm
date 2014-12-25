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
use Nextras\Orm\Entity\IPropertyHasRawValue;
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

	/** @var array */
	private $setterCall = [];

	/** @var array */
	private $getterCall = [];


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

		$this->metadata->getProperty($name); // checks property existance
		return isset($this->modified[NULL]) || isset($this->modified[$name]);
	}


	public function setAsModified($name = NULL)
	{
		$this->modified[$name] = TRUE;
		return $this;
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


	public function & getRawValue($name)
	{
		$propertyMetadata = $this->metadata->getProperty($name);
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyHasRawValue) {
			$value = $this->data[$name]->getRawValue();
			return $value;
		} else {
			return $this->data[$name];
		}
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
		foreach ($this->getMetadata()->getStorageProperties() as $property) {
			// getValue loads data & checks for not null values
			if ($this->getValue($property) && is_object($this->data[$property])) {
				if ($this->data[$property] instanceof IRelationshipCollection) {
					$data = iterator_to_array($this->data[$property]->get());
					$this->setValue('id', NULL);
					$this->data[$property] = clone $this->data[$property];
					$this->data[$property]->setParent($this);
					$this->data[$property]->set($data);
					$this->setValue('id', $id);

				} elseif ($this->data[$property] instanceof IRelationshipContainer) {
					$this->data[$property] = clone $this->data[$property];
					$this->data[$property]->setParent($this);

				} else {
					$this->data[$property] = clone $this->data[$property];
				}
			}
		}
		$this->setValue('id', NULL);
		parent::__clone();
	}


	public function serialize()
	{
		return [
			'parent' => parent::serialize(),
			'modified' => $this->modified,
			'validated' => $this->validated,
			'data' => $this->toArray(IEntity::TO_ARRAY_RELATIONSHIP_AS_ID),
		];
	}


	public function unserialize($unserialized)
	{
		parent::unserialize($unserialized['parent']);
		$this->modified = $unserialized['modified'];
		$this->validated = $unserialized['validated'];
		$this->data = $unserialized['data'];
	}


	public function __debugInfo()
	{
		return $this->data;
	}


	// === events ======================================================================================================


	protected function onLoad(IRepository $repository, EntityMetadata $metadata, array $data)
	{
		$this->metadata = $metadata;
		foreach ($metadata->getStorageProperties() as $property) {
			if (isset($data[$property])) {
				$this->data[$property] = $data[$property];
			}
		}

		parent::onLoad($repository, $metadata, $data);
	}


	protected function onAttach(IRepository $repository, EntityMetadata $metadata)
	{
		parent::onAttach($repository, $metadata);
		$this->metadata = $metadata;
	}


	protected function onPersist($id)
	{
		$this->setValue('id', $id);
		$this->modified = [];
		parent::onPersist($id);
	}


	// === internal implementation =====================================================================================


	protected function createMetadata()
	{
		return MetadataStorage::get(get_class($this));
	}


	private function internalSetValue(PropertyMetadata $metadata, $name, $value)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name);
		}

		if ($metadata->hasSetter && !isset($this->setterCall[$name])) {
			$this->setterCall[$name] = TRUE;
			call_user_func([$this, 'set' . $name], $value);
			unset($this->setterCall[$name]);
			return;
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			$this->data[$name]->setInjectedValue($value);

		} else {
			if (!$metadata->isValid($value)) {
				$class = get_class($this);
				throw new InvalidArgumentException("Value for {$class}::\${$name} property is invalid.");
			}
			$this->data[$name] = $value;
			$this->modified[$name] = TRUE;
		}
	}


	private function & internalGetValue(PropertyMetadata $propertyMetadata, $name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		if ($propertyMetadata->hasGetter && !isset($this->getterCall[$name])) {
			$this->getterCall[$name] = TRUE;
			$value = call_user_func([$this, 'get' . $name]);
			unset($this->getterCall[$name]);
			return $value;
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->getInjectedValue();

		} else {
			if (!isset($this->data[$name]) && !$propertyMetadata->isNullable) {
				$class = get_class($this);
				throw new InvalidStateException("Property {$class}::\${$name} is not set.");
			}
			return $this->data[$name];
		}
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

		if (!array_key_exists($name, $this->data)) {
			$this->data[$name] = $propertyMetadata->defaultValue;
		}

		if ($propertyMetadata->container) {
			$this->initPropertyObject($propertyMetadata, $name);

		} elseif ($this->data[$name] !== NULL) {
			$this->internalSetValue($propertyMetadata, $name, $this->data[$name]);
			unset($this->modified[$name]);
		}
	}


	private function initPropertyObject(PropertyMetadata $propertyMetadata, $name)
	{
		$class = $propertyMetadata->container;

		/** @var IProperty $property */
		$property = new $class($this, $propertyMetadata);
		$property->onModify(function() use ($name) {
			$this->modified[$name] = TRUE;
		});

		if ($this->isPersisted()) {
			$property->setLoadedValue($this->data[$name]);
		}

		$this->data[$name] = $property;
	}

}
