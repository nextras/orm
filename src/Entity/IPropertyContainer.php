<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;


interface IPropertyContainer extends IPropertyInjection
{
	/**
	 * Returns injected value.
	 * @internal
	 * @return mixed
	 */
	public function &getInjectedValue();


	/**
	 * Returns true wheter property container has a value.
	 * @internal
	 * @return bool
	 */
	public function hasInjectedValue();
}
