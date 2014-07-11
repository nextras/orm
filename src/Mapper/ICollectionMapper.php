<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper;

use Nextras\Orm\Entity\Collection\ICollection;
use Traversable;


interface ICollectionMapper
{

	/**
	 * Returns iterator.
	 * @return Traversable
	 */
	public function getIterator();


	/**
	 * Returns count of iterator entries.
	 * @return int
	 */
	public function getIteratorCount();


	/**
	 * Adds condition.
	 * @param  string   $column column or relationship chain
	 * @param  mixed    $value
	 * @return static
	 */
	public function addCondition($column, $value);


	/**
	 * Selects columns to order by.
	 * @param  string|array $column column name or array of column names
	 * @param  string       $direction sorting direction ICollection::ASC or ICollection::DESC
	 * @return static
	 */
	public function orderBy($column, $direction = ICollection::ASC);


	/**
	 * Limits number of rows.
	 * @param  int      $limit
	 * @param  int|NULL $offset
	 * @return static
	 */
	public function limitBy($limit, $offset = NULL);

}
