<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;


interface IPropertyInjection extends IProperty
{
	/**
	 * Sets value.
	 * @internal
	 * @param mixed $value
	 */
	public function setInjectedValue($value);
}
