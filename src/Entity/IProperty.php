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
	 * Adds onModify callback notification.
	 * @param  mixed $callback
	 */
	public function onModify($callback);


	/**
	 * Sets raw value (value loaded from database).
	 * Raw value is the same value as when the container has not yet been created.
	 * @param  mixed $value
	 */
	public function setRawValue($value);


	/**
	 * Returns raw value.
	 * Raw value is the same value as when the container has not yet been created.
	 * @return mixed
	 */
	public function getRawValue();

}
