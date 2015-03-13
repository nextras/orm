<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
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
	 * Raw value is normalized value which is suitable unique identification.
	 * @return mixed
	 */
	public function getRawValue();

}
