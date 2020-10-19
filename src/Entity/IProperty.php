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
	 * Sets raw value from storage.
	 * Calling this method directly is not recommended.
	 * Implementation must not require entity instance.
	 * @param mixed $value
	 * @internal
	 */
	public function setRawValue($value): void;


	/**
	 * Returns raw value.
	 * Raw value is normalized to be suitable for storing.
	 * @return mixed
	 */
	public function getRawValue();
}
