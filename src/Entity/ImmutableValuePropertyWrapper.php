<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\PropertyMetadata;


abstract class ImmutableValuePropertyWrapper implements IEntityAwareProperty, IPropertyContainer
{
	/** @var mixed */
	protected $value;

	/** @var PropertyMetadata */
	protected $propertyMetadata;

	/** @var IEntity */
	protected $entity;


	public function __construct(PropertyMetadata $propertyMetadata)
	{
		$this->propertyMetadata = $propertyMetadata;
	}


	public function setPropertyEntity(IEntity $entity)
	{
		$this->entity = $entity;
	}


	public function setRawValue($value): void
	{
		$this->value = $this->convertFromRawValue($value);
	}


	public function getRawValue()
	{
		return $this->convertToRawValue($this->value);
	}


	public function setInjectedValue($value): void
	{
		if ($this->isModified($this->value, $value)) {
			$this->entity->setAsModified($this->propertyMetadata->name);
		}
		$this->value = $value;
	}


	public function hasInjectedValue(): bool
	{
		return $this->value !== null;
	}


	public function &getInjectedValue()
	{
		return $this->value;
	}


	protected function isModified($oldValue, $newValue): bool
	{
		return $oldValue !== $newValue;
	}


	/**
	 * Converts passed value from raw value suitable for storing to runtime representation.
	 * This method cannot depend on entity instance.
	 * @internal
	 * @param  mixed $value
	 * @return mixed
	 */
	abstract public function convertFromRawValue($value);
}
