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
	 *
	 * Expectations:
	 * - Calling this method directly may BREAK things.
	 * - Implementation must not require entity instance.
	 * - Implementation must not validate the value (e.g. nullability of a non-nullable property); a raw value is set
	 *   during property instance creation and the missing value reflected as null is passed. Any validation is
	 *   therefore deferred to read time ({@see getRawValue()} / value read).
	 *
	 * This method is not symmetric to {@see getRawValue()}.
	 *
	 * @internal
	 * @param mixed $value
	 */
	public function setRawValue($value): void;


	/**
	 * Returns raw value.
	 *
	 * Raw value is a normalized and validated value which is suitable for storing.
	 * This method is not symmetric to {@see setRawValue()}.
	 *
	 * @return mixed
	 */
	public function getRawValue();
}
