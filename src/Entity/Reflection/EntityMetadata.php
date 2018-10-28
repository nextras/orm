<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;

use Nette\SmartObject;
use Nextras\Orm\Entity\Helpers\Typos;
use Nextras\Orm\InvalidArgumentException;


/**
 * @property-read string $className
 */
class EntityMetadata
{
	use SmartObject;


	/** @var string */
	private $className;

	/** @var array Primary key. */
	private $primaryKey = [];

	/** @var PropertyMetadata[] */
	private $properties = [];


	public function __construct(string $className)
	{
		$this->className = $className;
	}


	public function getClassName(): string
	{
		return $this->className;
	}


	public function setPrimaryKey(array $primaryKey): EntityMetadata
	{
		$this->primaryKey = $primaryKey;
		return $this;
	}


	public function getPrimaryKey(): array
	{
		return $this->primaryKey;
	}


	public function getProperty(string $name): PropertyMetadata
	{
		if (!isset($this->properties[$name])) {
			$closest = Typos::getClosest($name, array_keys($this->properties));
			throw new InvalidArgumentException(
				"Undefined property {$this->className}::\${$name}"
				. ($closest ? ", did you mean \$$closest?" : '.')
			);
		}

		return $this->properties[$name];
	}


	public function hasProperty(string $name): bool
	{
		return isset($this->properties[$name]);
	}


	public function setProperty(string $name, PropertyMetadata $property)
	{
		$this->properties[$name] = $property;
	}


	/**
	 * @return PropertyMetadata[]
	 */
	public function getProperties(): array
	{
		return $this->properties;
	}
}
