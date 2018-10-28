<?php declare(strict_types = 1);

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
use Nextras\Orm\Repository\Functions\ConjunctionOperatorFunction;
use Nextras\Orm\Repository\Functions\DisjunctionOperatorFunction;


interface ICollection extends IteratorAggregate, Countable
{
	/** @const asc order */
	const ASC = 'ASC';

	/** @const desc order */
	const DESC = 'DESC';

	/** @const and logic operator */
	const AND = ConjunctionOperatorFunction::class;

	/** @const or logic operator */
	const OR = DisjunctionOperatorFunction::class;


	/**
	 * Returns IEntity filtered by conditions.
	 * @param  array $where
	 */
	public function getBy(array $where): ?IEntity;


	/**
	 * Returns entity by primary value.
	 * @param  mixed $id
	 */
	public function getById($id): ?IEntity;


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
	 * Applies custom function to the collection.
	 * Returns new instance of collection.
	 */
	public function applyFunction(string $functionName, ...$args): ICollection;


	/**
	 * Fetches the first row.
	 */
	public function fetch(): ?IEntity;


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


	/**
	 * Sets relationship mapping over the collection.
	 * @internal
	 */
	public function setRelationshipMapper(IRelationshipMapper $mapper = null): ICollection;


	/**
	 * @internal
	 */
	public function getRelationshipMapper(): ?IRelationshipMapper;


	/**
	 * @internal
	 */
	public function setRelationshipParent(IEntity $parent): ICollection;


	/**
	 * Counts collection entities without fetching them from storage.
	 */
	public function countStored(): int;


	public function subscribeOnEntityFetch(callable $callback): void;
}
