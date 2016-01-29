<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;


interface IEntityIterator extends IEntityPreloadContainer, \Iterator, \Countable
{
	/**
	 * Sets index for inner hasMany collections.
	 * @param int|null  $index
	 */
	public function setDataIndex($index);
}
