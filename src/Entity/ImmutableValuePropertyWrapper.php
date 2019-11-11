<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\PropertyMetadata;


abstract class ImmutableValuePropertyWrapper implements IPropertyContainer
{
	/** @var mixed */
	protected $value;

	/** @var PropertyMetadata */
	protected $propertyMetadata;


	public function __construct(PropertyMetadata $propertyMetadata)
	{
		$this->propertyMetadata = $propertyMetadata;
	}


	public function setRawValue($value): void
	{
		$this->value = $this->convertFromRawValue($value);
	}


	public function getRawValue()
	{
		return $this->convertToRawValue($this->value);
	}


	public function setInjectedValue($value): bool
	{
		$this->value = $value;
		return true;
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
	 * Implementation must not require entity instance.
	 * @internal
	 * @param  mixed $value
	 * @return mixed
	 */
	abstract public function convertFromRawValue($value);
}
