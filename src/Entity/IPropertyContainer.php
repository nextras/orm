<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity;


interface IPropertyContainer extends IPropertyInjection
{

	/**
	 * Returns injected value.
	 * @internal
	 * @return mixed
	 */
	function getInjectedValue();

}
