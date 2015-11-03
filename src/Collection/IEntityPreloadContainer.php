<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;


interface IEntityPreloadContainer
{
	/**
	 * Returns array of $property values for preloading.
	 * @param  string   $property
	 * @return array
	 */
	public function getPreloadValues($property);


	/**
	 * Returns unique identification of the root container.
	 * @return string
	 */
	public function getIdentification();
}
