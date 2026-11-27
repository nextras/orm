<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use Nextras\Dbal\IConnection;


/**
 * Executes a batch of read queries against the database server behind a connection, possibly concurrently.
 * Implementations return raw rows only; they never hydrate entities.
 */
interface IQueryExecutor
{
	/**
	 * @template K of array-key
	 * @param array<K, AsyncQuery> $queries
	 * @return array<K, list<array<string, mixed>>> rows of each query, with the same keys in the same order
	 */
	public function executeAll(IConnection $connection, array $queries): array;
}
