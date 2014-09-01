<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Collection;


interface IEntityPreloadContainer
{

	/**
	 * Returns array of $property values for preloading.
	 * @param  string   $property
	 * @return array
	 */
	public function getPreloadValues($property);

}
