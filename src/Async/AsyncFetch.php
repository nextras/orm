<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use Closure;
use Nextras\Dbal\IConnection;


/**
 * A prepared read of a collection: the query to run on the connection and the callback that stores its rows.
 * Created by DbalCollection::prepareAsyncFetch() / prepareAsyncCount(), consumed by {@see CollectionFetcher}.
 */
final class AsyncFetch
{
	public function __construct(
		public readonly IConnection $connection,
		public readonly AsyncQuery $query,
		/** @var Closure(list<array<string, mixed>>): void */
		private readonly Closure $apply,
	)
	{
	}


	/**
	 * Stores the fetched rows in the collection, unless the collection was fetched or changed in the meantime;
	 * it then fetches lazily again, as usual.
	 * @param list<array<string, mixed>> $rows
	 */
	public function apply(array $rows): void
	{
		($this->apply)($rows);
	}
}
