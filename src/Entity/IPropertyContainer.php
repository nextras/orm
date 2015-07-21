<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;


interface IPropertyContainer extends IProperty
{

	/**
	 * Sets value.
	 * @internal
	 * @param mixed $value
	 */
	public function setInjectedValue($value);


	/**
	 * Returns injected value.
	 * @internal
	 * @return mixed
	 */
	public function & getInjectedValue();


	/**
	 * Returns wheter property container has a value.
	 * @return bool
	 */
	public function hasInjectedValue();

}
