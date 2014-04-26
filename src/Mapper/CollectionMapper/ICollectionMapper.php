<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\CollectionMapper;


interface ICollectionMapper
{

	/**
	 * Empties caches.
	 * @return mixed
	 */
	function release();


	/**
	 * Returns iterator.
	 * @return \Traversable
	 */
	function getIterator();


	/**
	 * Returns count of iterator entries.
	 * @return int
	 */
	function getIteratorCount();

}
