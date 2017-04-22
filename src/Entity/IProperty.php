<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\PropertyMetadata;


/**
 * A minimal interface for implementing property wrapper.
 */
interface IProperty
{
	public function __construct(PropertyMetadata $propertyMetadata);


	/**
	 * Converts passed value to raw value suitable for storing.
	 * This method cannot depend on entity's instance.
	 * @internal
	 * @param mixed $value
	 * @return mixed
	 */
	public function convertToRawValue($value);


	/**
	 * Sets raw value.
	 * @param mixed $value
	 */
	public function setRawValue($value): void;


	/**
	 * Returns raw value.
	 * Raw value is a normalized value which is suitable for storing.
	 * @return mixed
	 */
	public function getRawValue();
}
