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
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapper;


interface ICollection extends IteratorAggregate, Countable
{
	/** @const asc order */
	const ASC = 'ASC';

	/** @const desc order */
	const DESC = 'DESC';


	/**
	 * Returns IEntity filtered by conditions
	 * @param  array
	 * @return IEntity|NULL
	 */
	function getBy(array $where);


	/**
	 * Returns entity collection filtered by conditions
	 * Returns new instance of collection.
	 * @param  array
	 * @return ICollection
	 */
	function findBy(array $where);


	/**
	 * Selects columns to order by.
	 * Returns new instance of collection.
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
	function limitBy($limit, $offset = NULL);


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
	 * Fetches all records like $key => $value pairs.
	 * @param  string associative key
	 * @param  string value
	 * @return array
	 */
	function fetchPairs($key = NULL, $value = NULL);


	/**
	 * Returns collection
	 * @return ICollection
	 */
	function toCollection();


	/**
	 * @internal
	 * @ignore
	 * @return IRelationshipMapper
	 */
	function getRelationshipMapper();

}
