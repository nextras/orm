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


class EntityMetadata extends Object
{
	/** @var string */
	public $entityClass;

	/** @var array Primary key. */
	public $primaryKey = [];

	/** @var array Array of properties for entity persisting. */
	public $storageProperties = [];

	/** @var PropertyMetadata[] */
	private $properties = [];


	public function toArray()
	{
		$properties = [];
		foreach ($this->properties as $name => $property) {
			$properties[$name] = $property->toArray();
		}

		return (object) [
			'properties' => $properties,
		];
	}


	/**
	 * @param  string   $name
	 * @return PropertyMetadata
	 */
	public function getProperty($name)
	{
		if (!isset($this->properties[$name])) {
			throw new InvalidArgumentException("Undefined property $name.");
		}

		return $this->properties[$name];
	}


	public function hasProperty($name)
	{
		return isset($this->properties[$name]);
	}


	public function setProperty($name, PropertyMetadata $property)
	{
		$this->properties[$name] = $property;
		return $this;
	}


	public function getProperties()
	{
		return $this->properties;
	}

}
