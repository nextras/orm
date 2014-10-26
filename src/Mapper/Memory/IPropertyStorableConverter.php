<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Memory;


interface IPropertyStorableConverter
{

	/**
	 * Returns storable value.
	 * @return mixed
	 */
	public function getMemoryStorableValue();

}
