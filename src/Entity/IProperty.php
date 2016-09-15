<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\PropertyMetadata;


interface IProperty
{
	public function __construct(IEntity $entity, PropertyMetadata $propertyMetadata);


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
