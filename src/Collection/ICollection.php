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


/**
 * @extends IteratorAggregate<int, IEntity>
 */
interface ICollection extends IteratorAggregate, Countable
{
	/** @const ascending order, nulls ordering is undefined and depends on storage || ICollection implementation */
	const ASC = 'ASC';
	/** @const descending order, nulls ordering is undefined and depends on storage || ICollection implementation */
	const DESC = 'DESC';
	/** @const ascending order, nulls are at the end */
	const ASC_NULLS_LAST = 'ASC_NULLS_LAST';
	/** @const ascending order, nulls are at the beginning */
	const ASC_NULLS_FIRST = 'ASC_NULLS_FIRST';
	/** @const descending order, nulls are at the end */
	const DESC_NULLS_LAST = 'DESC_NULLS_LAST';
	/** @const descending order, nulls are at the beginning */
	const DESC_NULLS_FIRST = 'DESC_NULLS_FIRST';

	/** @const and logic operator */
	const AND = ConjunctionOperatorFunction::class;
	/** @const or logic operator */
	const OR = DisjunctionOperatorFunction::class;


	/**
	 * Returns IEntity filtered by conditions.
	 * @param array $where
	 */
	public function getBy(array $where): ?IEntity;


	/**
	 * Returns entity by primary value.
	 * @param mixed $id
	 */
	public function getById($id): ?IEntity;


	/**
	 * Returns entity collection filtered by conditions.
	 * Returns new instance of collection.
	 */
	public function findBy(array $where): ICollection;


	/**
	 * Orders collection by column.
	 * Returns new instance of collection.
	 * @param string $propertyPath property name or property path expression (property->property)
	 * @param string $direction    sorting direction self::ASC or self::DESC
	 */
	public function orderBy(string $propertyPath, string $direction = self::ASC): ICollection;


	/**
	 * Orders collection by multiple column orderings.
	 * @param  array<string, string> $properties (key - property name, value - property sorting direction)
	 * @return ICollection
	 */
	public function orderByMultiple(array $properties): ICollection;


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
	 * @param string|null $key   associative key
	 * @param string|null $value value
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
