<?php declare(strict_types = 1);

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
	public function &getInjectedValue(IEntity $entity);


	/**
	 * Returns true wheter property container has a value.
	 * @internal
	 */
	public function hasInjectedValue(IEntity $entity): bool;
}
