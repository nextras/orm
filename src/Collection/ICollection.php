<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use Countable;
use IteratorAggregate;
use Nextras\Orm\Collection\Functions\ConjunctionOperatorFunction;
use Nextras\Orm\Collection\Functions\DisjunctionOperatorFunction;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\NoResultException;


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
	 * Returns IEntity filtered by conditions, null if none found.
	 */
	public function getBy(array $conds): ?IEntity;


	/**
	 * Returns IEntity filtered by conditions, throw if none found.
	 * @throws NoResultException
	 */
	public function getByChecked(array $conds): IEntity;


	/**
	 * Returns entity by primary value, null if none found.
	 * @param mixed $id
	 */
	public function getById($id): ?IEntity;


	/**
	 * Returns entity by primary value, throws if none found.
	 * @param mixed $id
	 * @throws NoResultException
	 */
	public function getByIdChecked($id): IEntity;


	/**
	 * Returns entity collection filtered by conditions.
	 * Returns new instance of collection.
	 * @return static
	 */
	public function findBy(array $conds): ICollection;


	/**
	 * Orders collection by column.
	 * Returns new instance of collection.
	 * @param string|array $expression property name or property path expression (property->property) or "expression function" array expression.
	 * @param string $direction the sorting direction self::ASC or self::DESC, etc.
	 * @return static
	 */
	public function orderBy($expression, string $direction = self::ASC): ICollection;


	/**
	 * Resets collection ordering.
	 * @return static
	 */
	public function resetOrderBy(): ICollection;


	/**
	 * Limits number of rows.
	 * @return static
	 */
	public function limitBy(int $limit, int $offset = null): ICollection;


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
	 * @return static
	 */
	public function setRelationshipMapper(IRelationshipMapper $mapper = null): ICollection;


	/**
	 * @internal
	 */
	public function getRelationshipMapper(): ?IRelationshipMapper;


	/**
	 * @internal
	 * @return static
	 */
	public function setRelationshipParent(IEntity $parent): ICollection;


	/**
	 * Counts collection entities without fetching them from storage.
	 */
	public function countStored(): int;


	/**
	 * @phpstan-param callable(\Traversable<IEntity>):void $callback
	 */
	public function subscribeOnEntityFetch(callable $callback): void;
}
