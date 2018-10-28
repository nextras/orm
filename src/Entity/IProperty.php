<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\PropertyMetadata;


interface IProperty
{
	public function __construct(PropertyMetadata $propertyMetadata);


	/**
	 * @internal
	 */
	public function loadValue(IEntity $entity, array $values): void;


	/**
	 * @internal
	 */
	public function saveValue(IEntity $entity, array $values): array;


	/**
	 * @internal
	 * @param  mixed $value
	 * @return mixed
	 */
	public function convertToRawValue($value);


	/**
	 * Sets raw value.
	 * @param  mixed $value
	 */
	public function setRawValue($value);


	/**
	 * Returns raw value.
	 * Raw value is a normalized value which is suitable for unique identification and storing.
	 * @return mixed
	 */
	public function getRawValue();
}
