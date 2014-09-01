<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Reflection;

use Nette\Object;
use Nextras\Orm\InvalidArgumentException;


/**
 * @property-read string $className
 */
class EntityMetadata extends Object
{
	/** @var string */
	private $className;

	/** @var array Primary key. */
	private $primaryKey = [];

	/** @var array Array of properties for entity persisting. */
	private $storageProperties = [];

	/** @var PropertyMetadata[] */
	private $properties = [];


	public function __construct($className)
	{
		$this->className = $className;
	}


	public function getClassName()
	{
		return $this->className;
	}


	public function setStorageProperties(array $storageProperties)
	{
		$this->storageProperties = $storageProperties;
	}


	public function getStorageProperties()
	{
		return $this->storageProperties;
	}


	public function setPrimaryKey(array $primaryKey)
	{
		$this->primaryKey = $primaryKey;
		return $this;
	}


	public function getPrimaryKey()
	{
		return $this->primaryKey;
	}


	/**
	 * @param  string   $name
	 * @return PropertyMetadata
	 */
	public function getProperty($name)
	{
		if (!isset($this->properties[$name])) {
			throw new InvalidArgumentException("Undefined property '$name'.");
		}

		return $this->properties[$name];
	}


	/**
	 * @param  string   $name
	 * @return bool
	 */
	public function hasProperty($name)
	{
		return isset($this->properties[$name]);
	}


	/**
	 * @param string            $name
	 * @param PropertyMetadata  $property
	 */
	public function setProperty($name, PropertyMetadata $property)
	{
		$this->properties[$name] = $property;
	}


	/**
	 * @return PropertyMetadata[]
	 */
	public function getProperties()
	{
		return $this->properties;
	}

}
