<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Collection;


interface IEntityIterator extends IEntityPreloadContainer, \Iterator, \Countable
{

	/**
	 * Sets index for inner hasMany collections.
	 * @param int|NULL  $index
	 */
	public function setDataIndex($index);

}


