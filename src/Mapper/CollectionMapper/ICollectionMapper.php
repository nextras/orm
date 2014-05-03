<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\CollectionMapper;

use Nextras\Orm\Entity\Collection\ICollection;


interface ICollectionMapper
{

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


	/**
	 * Adds condition.
	 * @param  string  column or relationship chain
	 * @param  mixed
	 * @return static
	 */
	function addCondition($column, $value);


	/**
	 * Selects columns to order by.
	 * @param  string|array column name or array of column names
	 * @param  string sorting direction ICollection::ASC or ICollection::DESC
	 * @return static
	 */
	function orderBy($column, $direction = ICollection::ASC);


	/**
	 * Limits number of rows.
	 * @param  int
	 * @param  int
	 * @return static
	 */
	function limitBy($limit, $offset = NULL);

}
