<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\NullValueException;


abstract class ImmutableValuePropertyWrapper implements IPropertyContainer
{
	/** @var mixed converted runtime value; meaningful only when {@see $validated} is true */
	protected $value;

	/** @var mixed raw value pending conversion; meaningful only when {@see $validated} is false */
	private $rawValue;

	/** whether the currently held value has already been converted and validated */
	private bool $validated = true;


	public function __construct(
		protected readonly PropertyMetadata $propertyMetadata,
	)
	{
	}


	/**
	 * Sets a fetched raw value.
	 *
	 * The raw value is stored as-is; it is neither converted nor validated here. Both the conversion to the runtime
	 * representation and the nullability validation are deferred to read time ({@see getInjectedValue()} /
	 * {@see getRawValue()}), therefore a raw value may be set even before it is known whether it is valid.
	 */
	public function setRawValue($value): void
	{
		$this->rawValue = $value;
		$this->validated = false;
	}


	public function getRawValue()
	{
		return $this->convertToRawValue($this->getInjectedValue());
	}


	public function setInjectedValue($value): bool
	{
		$this->value = $value;
		$this->rawValue = null;
		$this->validated = true;
		return true;
	}


	public function hasInjectedValue(): bool
	{
		return ($this->validated ? $this->value : $this->rawValue) !== null;
	}


	public function &getInjectedValue()
	{
		if (!$this->validated) {
			$this->value = $this->convertFromRawValue($this->rawValue);
			$this->rawValue = null;
			$this->validated = true;
		}
		if ($this->value === null && !$this->propertyMetadata->isNullable) {
			throw new NullValueException($this->propertyMetadata);
		}
		return $this->value;
	}


	/**
	 * Converts passed value from raw value suitable for storing to runtime representation.
	 *
	 * - Implementation must not require entity instance.
	 * - The conversion is performed lazily on read; it must return null for a null input (nullability is validated
	 *   centrally) but is allowed to throw for an otherwise invalid value.
	 *
	 * @param mixed $value
	 * @return mixed
	 * @internal
	 */
	abstract public function convertFromRawValue($value);
}
