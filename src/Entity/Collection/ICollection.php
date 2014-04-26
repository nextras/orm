<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Collection;

use Countable;
use IteratorAggregate;
use Nextras\Orm;
use Nextras\Orm\Entity\IEntity;


interface ICollection extends IteratorAggregate, Countable
{
	/** @const asc order */
	const ASC = 'ASC';

	/** @const desc order */
	const DESC = 'DESC';


	/**
	 * Selects columns to order by.
	 * @param  string|array column name or array of column names
	 * @param  string sorting direction self::ASC or self::DESC
	 * @return static
	 */
	function orderBy($column, $direction = self::ASC);


	/**
	 * Limits number of rows.
	 * @param  int
	 * @param  int
	 * @return static
	 */
	function limit($limit, $offset = NULL);


	/**
	 * Fetches the first row.
	 * @return IEntity|NULL
	 */
	function fetch();


	/**
	 * Fetches all records.
	 * @return IEntity[]
	 */
	function fetchAll();


	/**
	 * Fetches all records and returns associative tree.
	 * @param  string associative descriptor
	 * @return IEntity[]
	 */
	function fetchAssoc($assoc);


	/**
	 * Fetches all records like $key => $value pairs.
	 * @param  string associative key
	 * @param  string value
	 * @return array
	 */
	function fetchPairs($key = NULL, $value = NULL);


	/**
	 * Returns entity collection filtered by conditions
	 * @param  array
	 * @return ICollection
	 */
	function findBy(array $where);


	/**
	 * Returns IEntity filtered by conditions
	 * @param  array
	 * @return IEntity|NULL
	 */
	function getBy(array $where);


	/**
	 * Returns collection
	 * @return ICollection
	 */
	function toCollection();

}
