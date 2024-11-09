<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Countable;
use Iterator;
use IteratorAggregate;
use Nextras\Orm\Collection\Functions\ConjunctionOperatorFunction;
use Nextras\Orm\Collection\Functions\DisjunctionOperatorFunction;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Mapper\IRelationshipMapper;


/**
 * @template E of IEntity
 * @extends IteratorAggregate<int, E>
 */
interface ICollection extends IteratorAggregate, Countable
{
	/** @const ascending order, nulls ordering is undefined and depends on storage || ICollection implementation */
	public const ASC = 'ASC';
	/** @const descending order, nulls ordering is undefined and depends on storage || ICollection implementation */
	public const DESC = 'DESC';
	/** @const ascending order, nulls are at the end */
	public const ASC_NULLS_LAST = 'ASC_NULLS_LAST';
	/** @const ascending order, nulls are at the beginning */
	public const ASC_NULLS_FIRST = 'ASC_NULLS_FIRST';
	/** @const descending order, nulls are at the end */
	public const DESC_NULLS_LAST = 'DESC_NULLS_LAST';
	/** @const descending order, nulls are at the beginning */
	public const DESC_NULLS_FIRST = 'DESC_NULLS_FIRST';

	/** @const and logic operator */
	public const AND = ConjunctionOperatorFunction::class;
	/** @const or logic operator */
	public const OR = DisjunctionOperatorFunction::class;


	/**
	 * Returns IEntity filtered by conditions, null if none found.
	 *
	 * Limits collection via {@see ICollection::findBy()} and returns the first entity (or null).
	 *
	 * @param array<string, mixed>|array<mixed> $conds
	 * @return E|null
	 */
	public function getBy(array $conds): ?IEntity;


	/**
	 * Returns IEntity filtered by conditions, throw if none found.
	 *
	 * Limits collection via {@see ICollection::findBy()} and returns the first entity (or throw).
	 *
	 * @param array<string, mixed>|array<mixed> $conds
	 * @throws NoResultException
	 * @return E
	 */
	public function getByChecked(array $conds): IEntity;


	/**
	 * Returns entity by primary value, null if none found.
	 * @param mixed $id
	 * @return E|null
	 */
	public function getById($id): ?IEntity;


	/**
	 * Returns entity by primary value, throws if none found.
	 * @param mixed $id
	 * @throws NoResultException
	 * @return E
	 */
	public function getByIdChecked($id): IEntity;


	/**
	 * Returns entity collection filtered by conditions.
	 * Returns new instance of collection.
	 *
	 * There are three types of supported conditions:
	 *
	 * Implicit {@see ICollection::AND} function:
	 * <code>
	 * [
	 *      'property' => 'value
	 * ]
	 * </code>
	 *
	 * Explicit function with inlined arguments:
	 * <code>
	 * [
	 *      ICollection::OR,
	 *      'property1' => 'value',
	 *      'property2' => 'value',
	 * ]
	 * </code>
	 *
	 * Explicit function with non-inlined arguments:
	 * <code>
	 * [
	 *      ICollection::OR,
	 *      ['property' => 'value1'],
	 *      ['property' => 'value2'],
	 * ]
	 * </code>
	 * @param array<mixed> $conds
	 * @return static
	 */
	public function findBy(array $conds): ICollection;


	/**
	 * Orders collection by column.
	 * Returns new instance of collection.
	 *
	 * There are two types of supported ordering expression:
	 *
	 * Explicit column:
	 * <code>
	 * orderBy('property', ICollection::ASC)
	 * </code>
	 *
	 * Explicit columns with their ordering:
	 * <code>
	 * orderBy([
	 *      'property1' => ICollection::ASC,
	 *      'property2' => ICollection::DESC,
	 * ])
	 * </code>
	 *
	 * Collection function result:
	 * <code>
	 * orderBy(
	 *      [CountAggregateFunction::class, 'property->id'],
	 *      ICollection::DESC
	 * )
	 * </code>
	 * @param string|array<string, string>|list<mixed> $expression property name or property path expression (property->property) or "collection function" array expression.
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
	public function limitBy(int $limit, int|null $offset = null): ICollection;


	/**
	 * Fetches the first row.
	 * @return E|null
	 */
	public function fetch(): ?IEntity;


	/**
	 * Fetches the first row., throw if none found.
	 * @throws NoResultException
	 * @return E
	 */
	public function fetchChecked(): IEntity;


	/**
	 * Fetches all records.
	 * @return list<E>
	 */
	public function fetchAll(): array;


	/**
	 * Fetches all records like $key => $value pairs.
	 * @param string|null $key associative key
	 * @param string|null $value value
	 * @return array<int|string, mixed>
	 */
	public function fetchPairs(string|null $key = null, string|null $value = null): array;


	/**
	 * @return Iterator<int, E>
	 */
	public function getIterator(): Iterator;


	/**
	 * Fetches requested data and returns MemoryCollection instance with the fetched entities.
	 * @return MemoryCollection<E>
	 */
	public function toMemoryCollection(): MemoryCollection;


	/**
	 * Sets relationship mapping over the collection.
	 * @return static
	 * @internal
	 */
	public function setRelationshipMapper(IRelationshipMapper|null $mapper): ICollection;


	/**
	 * @internal
	 */
	public function getRelationshipMapper(): ?IRelationshipMapper;


	/**
	 * @return static
	 * @internal
	 */
	public function setRelationshipParent(IEntity $parent): ICollection;


	/**
	 * Counts collection entities without fetching them from storage.
	 */
	public function countStored(): int;


	/**
	 * @param callable(\Traversable<E>):void $callback
	 */
	public function subscribeOnEntityFetch(callable $callback): void;
}
