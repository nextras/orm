<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


use Nextras\Orm\Entity\Reflection\PropertyMetadata;


/**
 * Minimal interface for implementing a property wrapper.
 */
interface IProperty
{
	public function __construct(PropertyMetadata $propertyMetadata);


	/**
	 * Converts passed value to raw value suitable for storing.
	 * Implementation must not require entity instance.
	 * @param mixed $value
	 * @return mixed
	 * @internal
	 */
	public function convertToRawValue($value);


	/**
	 * Sets a fetched raw value from a storage.
	 * Calling this method directly may BREAK things.
	 * Implementation must not require entity instance.
	 * This method is not symmetric to {@see getRawValue()}.
	 * @param mixed $value
	 * @internal
	 */
	public function setRawValue($value): void;


	/**
	 * Returns raw value.
	 * Raw value is a normalized value which is suitable for storing.
	 * This method is not symmetric to {@see setRawValue()}.
	 * @return mixed
	 */
	public function getRawValue();
}
