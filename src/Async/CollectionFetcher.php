<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use Closure;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Exception\InvalidStateException;
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
		/** @var array<int, array{IConnection, array<array-key, AsyncQuery>, array<array-key, Closure(list<array<string, mixed>>): void>}> $groups */
		$groups = [];

		foreach ($requests as $key => $request) {
			if ($request instanceof AsyncCount) {
				$collection = $request->collection;
				if (!$collection instanceof DbalCollection || $collection->isCountFetched()) {
					$request->set($collection->countStored());
					continue;
				}
				$query = $collection->getCountQuery();
				$apply = static function (array $rows) use ($collection, $request): void {
					$count = $rows[0]['count'] ?? throw new InvalidStateException('Unable to fetch collection count.');
					$collection->setFetchedCount((int) $count);
					$request->set((int) $count);
				};
			} else {
				if (!$request instanceof DbalCollection || $request->isFetched()) {
					continue;
				}
				$collection = $request;
				$query = $collection->getFetchQuery();
				$apply = static function (array $rows) use ($collection): void {
					/** @var list<array<string, mixed>> $rows */
					$collection->setFetchedRows($rows);
				};
			}

			$connection = $collection->getConnection();
			$groupId = spl_object_id($connection);
			$groups[$groupId][0] = $connection;
			$groups[$groupId][1][$key] = $query;
			$groups[$groupId][2][$key] = $apply;
		}

		foreach ($groups as [$connection, $queries, $appliers]) {
			$rows = $this->executor->executeAll($connection, $queries);
			foreach ($appliers as $key => $apply) {
				$apply($rows[$key]);
			}
		}
	}
}
