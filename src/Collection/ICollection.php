<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use Countable;
use Iterator;
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
	 * Returns IEntity filtered by conditions.
	 * @param  array $where
	 * @return IEntity|null
	 */
	public function getBy(array $where);


	/**
	 * Returns entity collection filtered by conditions.
	 * Returns new instance of collection.
	 */
	public function findBy(array $where): ICollection;


	/**
	 * Selects columns to order by.
	 * Returns new instance of collection.
	 * @param  string|array $column column name or array of column names
	 * @param  string       $direction sorting direction self::ASC or self::DESC
	 */
	public function orderBy($column, string $direction = self::ASC): ICollection;


	/**
	 * Resets collection ordering.
	 */
	public function resetOrderBy(): ICollection;


	/**
	 * Limits number of rows.
	 */
	public function limitBy(int $limit, int $offset = null): ICollection;


	/**
	 * Fetches the first row.
	 * @return IEntity|null
	 */
	public function fetch();


	/**
	 * Fetches all records.
	 * @return IEntity[]
	 */
	public function fetchAll();


	/**
	 * Fetches all records like $key => $value pairs.
	 * @param  string|null $key associative key
	 * @param  string|null $value value
	 */
	public function fetchPairs(string $key = null, string $value = null): array;


	public function getEntityIterator(IEntity $parent = null): Iterator;


	/**
	 * @param  IEntity|null $parent
	 */
	public function getEntityCount(IEntity $parent = null): int;


	/**
	 * Sets relationship mapping over collection.
	 * @internal
	 * @ignore
	 * @param  IRelationshipMapper|null $mapper
	 * @param  IEntity|null             $parent
	 */
	public function setRelationshipMapping(IRelationshipMapper $mapper = null, IEntity $parent = null): ICollection;


	/**
	 * @internal
	 * @ignore
	 */
	public function getRelationshipMapper(): IRelationshipMapper;


	/**
	 * Counts collection entities without fetching them from storage.
	 */
	public function countStored(): int;
}
