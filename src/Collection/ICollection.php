<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use Countable;
use IteratorAggregate;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Traversable;


interface ICollection extends IteratorAggregate, Countable
{
	/** @const asc order */
	const ASC = 'ASC';

	/** @const desc order */
	const DESC = 'DESC';


	/**
	 * Returns IEntity filtered by conditions.
	 * @param  array $where
	 * @return IEntity|NULL
	 */
	public function getBy(array $where);


	/**
	 * Returns entity collection filtered by conditions.
	 * Returns new instance of collection.
	 * @param  array $where
	 * @return ICollection
	 */
	public function findBy(array $where);


	/**
	 * Selects columns to order by.
	 * Returns new instance of collection.
	 * @param  string|array $column column name or array of column names
	 * @param  string       $direction sorting direction self::ASC or self::DESC
	 * @return ICollection
	 */
	public function orderBy($column, $direction = self::ASC);


	/**
	 * Resets collection ordering.
	 * @return ICollection
	 */
	public function resetOrderBy();


	/**
	 * Limits number of rows.
	 * @param  int  $limit
	 * @param  int  $offset
	 * @return ICollection
	 */
	public function limitBy($limit, $offset = NULL);


	/**
	 * Fetches the first row.
	 * @return IEntity|NULL
	 */
	public function fetch();


	/**
	 * Fetches all records.
	 * @return IEntity[]
	 */
	public function fetchAll();


	/**
	 * Fetches all records like $key => $value pairs.
	 * @param  string  $key associative key
	 * @param  string  $value value
	 * @return array
	 */
	public function fetchPairs($key = NULL, $value = NULL);


	/**
	 * @param  IEntity|NULL $parent
	 * @return Traversable
	 */
	public function getEntityIterator(IEntity $parent = NULL);


	/**
	 * @param  IEntity|NULL $parent
	 * @return int
	 */
	public function getEntityCount(IEntity $parent = NULL);


	/**
	 * Sets relationship mapping over collection.
	 * @internal
	 * @ignore
	 * @param  IRelationshipMapper|NULL $mapper
	 * @param  IEntity|NULL             $parent
	 * @return self
	 */
	public function setRelationshipMapping(IRelationshipMapper $mapper = NULL, IEntity $parent = NULL);


	/**
	 * @internal
	 * @ignore
	 * @return IRelationshipMapper
	 */
	public function getRelationshipMapper();


	/**
	 * Counts collection entities without fetching them from storage.
	 * @return int
	 */
	public function countStored();


}
