<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;


/**
 * Property container supporting read via direct propery read on entity.
 */
interface IPropertyContainer extends IPropertyInjection
{
	/**
	 * Returns injected value.
	 * @internal
	 * @return mixed
	 */
	public function &getInjectedValue();


	/**
	 * Returns true if property container has a value.
	 * @internal
	 */
	public function hasInjectedValue(): bool;
}
