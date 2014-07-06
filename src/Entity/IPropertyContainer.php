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
	 * @param mixed
	 */
	function setInjectedValue($value);


	/**
	 * Returns injected value.
	 * @internal
	 * @return mixed
	 */
	function getInjectedValue();


	/**
	 * Returns true if modified.
	 * @return bool
	 */
	function isModified();

}
