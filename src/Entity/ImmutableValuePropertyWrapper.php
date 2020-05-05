<?php declare(strict_types = 1);

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


	/**
	 * Converts passed value from raw value suitable for storing to runtime representation.
	 * Implementation must not require entity instance.
	 * @param mixed $value
	 * @return mixed
	 * @internal
	 */
	abstract public function convertFromRawValue($value);
}
