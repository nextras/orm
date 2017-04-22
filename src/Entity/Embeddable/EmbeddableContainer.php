<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Embeddable;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;
use ReflectionClass;


class EmbeddableContainer implements IPropertyContainer
{
	/** @var IEntity */
	private $entity;

	/** @var PropertyMetadata */
	private $metadata;

	/** @var PropertyMetadata[] */
	private $properties = [];

	/** @var IEmbeddable|null */
	private $value;

	/** @var string */
	private $instanceType;


	public function __construct(IEntity $entity, PropertyMetadata $propertyMetadata)
	{
		$this->entity = $entity;
		$this->metadata = $propertyMetadata;
		if (!isset($this->metadata->args[EmbeddableContainer::class])) {
			throw new InvalidStateException();
		}

		$this->instanceType = $this->metadata->args[EmbeddableContainer::class]['class'];
		$this->properties = MetadataStorage::get($this->instanceType)->getProperties();
	}


	public function loadValue(array $values)
	{
		$data = [];
		$prefix = $this->metadata->name . '_';
		foreach ($this->properties as $property) {
			$data[$property->name] = $values[$prefix . $property->name] ?? null;
		}

		$this->setRawValue($data);
	}


	public function saveValue(array $values): array
	{
		return $this->getRawValue() + $values;
	}


	public function setRawValue($value)
	{
		$filtered = array_filter($value, function ($val) { return $val !== null; });
		if ($filtered) {
			$reflection = new ReflectionClass($this->instanceType);
			$embeddable = $reflection->newInstanceWithoutConstructor();
			assert($embeddable instanceof IEmbeddable);
			$embeddable->onLoad($value);
		} else {
			$embeddable = null;
		}

		$this->setInjectedValue($embeddable);
	}


	public function getRawValue()
	{
		$value = [];
		$prefix = $this->metadata->name . '_';
		foreach ($this->properties as $property) {
			$name = $prefix . $property->name;
			$value[$name] = $this->value ? $this->value->getValue($property->name) : null;
		}
		return $value;
	}


	public function &getInjectedValue()
	{
		return $this->value;
	}


	public function hasInjectedValue(): bool
	{
		return $this->value !== null;
	}


	public function setInjectedValue($value)
	{
		if ($value !== null && !$value instanceof $this->instanceType) {
			throw new InvalidArgumentException("Passed embeddable has to be instance of {$this->instanceType}.");
		}

		$this->value = $value;
		$this->entity->setAsModified($this->metadata->name);
	}
}
