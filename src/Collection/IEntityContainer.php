<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;


interface IEntityContainer extends IEntityPreloadContainer
{
	/**
	 * Returms entity by joining key.
	 * @param int   $key
	 */
	public function getEntity($key);
}
