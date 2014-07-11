<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity;


interface IPropertyInjection
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
	public function getInjectedValue();


	/**
	 * Returns storable value.
	 */
	public function getStorableValue();


	/**
	 * Returns true if modified.
	 * @return bool
	 */
	public function isModified();

}
