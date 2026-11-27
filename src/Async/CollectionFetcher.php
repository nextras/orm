<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use Nextras\Dbal\IConnection;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Collection\HasManyCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Exception\NotSupportedException;
use function array_map;
use function spl_object_id;


/**
 * Fetches several collections (and their stored counts) at once.
 *
 * The SQL of all requests sharing a connection is handed to one {@see IQueryExecutor} call, which may run it
 * concurrently. Entities are always hydrated afterwards, serially, in the calling coroutine, so the identity map
 * is never accessed concurrently.
 *
 * ```php
 * ['items' => $items, 'total' => $total] = $fetcher->fetchAll([
 *     'items' => $filtered->limitBy(20, $offset),
 *     'total' => CollectionFetcher::count($filtered),
 * ]);
 * foreach ($items as $item) { ... } // no query
 * $total->get(); // int
 * ```
 */
final class CollectionFetcher
{
	public function __construct(
		private readonly IQueryExecutor $executor = new SequentialQueryExecutor(),
	)
	{
	}


	/**
	 * @param ICollection<*> $collection
	 */
	public static function count(ICollection $collection): AsyncCount
	{
		return new AsyncCount($collection);
	}


	/**
	 * Fetches all passed collections in place and resolves all {@see AsyncCount} requests; returns the passed array,
	 * so it can be destructured. Collections not backed by DBAL (e.g. array collections) and already fetched
	 * collections are left as they are; relationship collections are not supported.
	 *
	 * @template T of array<array-key, ICollection<*>|AsyncCount>
	 * @param T $requests
	 * @return T
	 */
	public function fetchAll(array $requests): array
	{
		$this->fetch($requests);
		return $requests;
	}


	/**
	 * @param array<array-key, ICollection<*>|AsyncCount> $requests
	 */
	private function fetch(array $requests): void
	{
		/** @var array<int, array{IConnection, list<AsyncFetch>}> $groups */
		$groups = [];
		/** @var array<int, true> $planned keys: spl_object_id of a collection, negated for its count */
		$planned = [];
		/** @var list<AsyncCount> $counts */
		$counts = [];

		foreach ($requests as $request) {
			$isCount = $request instanceof AsyncCount;
			$collection = $isCount ? $request->collection : $request;
			if ($isCount) {
				$counts[] = $request;
			}

			if ($collection instanceof HasManyCollection) {
				throw new NotSupportedException('Relationship collections cannot be fetched asynchronously.');
			} elseif (!$collection instanceof DbalCollection) {
				continue; // e.g. array collections, nothing to fetch from a database
			}

			$id = $isCount ? -spl_object_id($collection) - 1 : spl_object_id($collection);
			if (isset($planned[$id])) {
				continue; // the same collection requested again
			}
			$planned[$id] = true;

			$fetch = $isCount ? $collection->prepareAsyncCount() : $collection->prepareAsyncFetch();
			if ($fetch === null) {
				continue; // already fetched
			}
			$groupId = spl_object_id($fetch->connection);
			$groups[$groupId][0] = $fetch->connection;
			$groups[$groupId][1][] = $fetch;
		}

		foreach ($groups as [$connection, $fetches]) {
			$rows = $this->executor->executeAll($connection, array_map(fn(AsyncFetch $fetch) => $fetch->query, $fetches));
			foreach ($fetches as $i => $fetch) {
				$fetch->apply($rows[$i]);
			}
		}

		foreach ($counts as $count) {
			$count->set($count->collection->countStored()); // stored by the fetch above, unless the collection changed
		}
	}
}
