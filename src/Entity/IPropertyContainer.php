<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity;


interface IPropertyContainer
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
	 * @param  bool
	 * @return mixed
	 */
	public function getInjectedValue($allowNull = FALSE);


	/**
	 * Returns true if modified.
	 * @return bool
	 */
	public function isModified();

}
