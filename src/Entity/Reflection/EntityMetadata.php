<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
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


	public function setPrimaryKey(array $primaryKey)
	{
		$this->primaryKey = $primaryKey;
		return $this;
	}


	/**
	 * @return array
	 */
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
			throw new InvalidArgumentException("Undefined property {$this->className}::\${$name}.");
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
