<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use Nextras\Dbal\Connection;
use Nextras\Dbal\IAsyncConnection;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\Result\PendingResult;
use Nextras\Dbal\Result\Result;
use Nextras\Orm\Exception\InvalidArgumentException;
use Throwable;
use WeakMap;
use function array_chunk;
use function array_slice;
use function count;
use function is_string;


/**
 * Runs queries concurrently without an event loop, so it works on PHP-FPM as well: every query is sent by
 * {@see IAsyncConnection::queryArgsAsync()} on its own connection, then the results are collected in order.
 * The database server executes the queries in parallel; fetching rows stays serial.
 *
 * The first query uses the main connection, the others use extra connections opened lazily with the main
 * connection's config (optionally overridden by $extraConnectionConfig, e.g. `['host' => 'p:db']` for persistent
 * mysqli connections) and kept for the lifetime of this executor; they are replaced when the main connection's
 * config changes.
 *
 * Requires a connection with async support (nextras/dbal: mysqli with mysqlnd, pgsql). The queries run sequentially
 * on the main connection otherwise, for a driver instance passed in the config (it cannot be shared by several
 * connections), inside a transaction started through DBAL (extra connections would not see uncommitted data) and
 * for a single query. Queries on the extra connections are reported to their own loggers only.
 */
final class NativeAsyncQueryExecutor implements IQueryExecutor
{
	/** @var WeakMap<IAsyncConnection, array{array<string, mixed>, list<Connection>}> main connection => its config and extra connections */
	private WeakMap $extraConnections;


	public function __construct(
		/** Maximum number of connections (including the main one) used at once. */
		private readonly int $maxConnections = 4,
		/** @var array<string, mixed> */
		private readonly array $extraConnectionConfig = [],
	)
	{
		if ($maxConnections < 1) {
			throw new InvalidArgumentException('At least one connection is required.');
		} elseif (isset($extraConnectionConfig['driver']) && !is_string($extraConnectionConfig['driver'])) {
			throw new InvalidArgumentException('A driver instance cannot be shared by the extra connections, pass a driver name.');
		}
		$this->extraConnections = new WeakMap();
	}


	public function executeAll(IConnection $connection, array $queries): array
	{
		if (
			count($queries) < 2
			|| $this->maxConnections < 2
			|| !$connection instanceof IAsyncConnection
			|| !$connection->supportsAsyncQueries()
			|| $connection->getTransactionNestedIndex() > 0
			|| !is_string($connection->getConfig()['driver'] ?? null) // a driver instance cannot be shared
		) {
			return (new SequentialQueryExecutor())->executeAll($connection, $queries);
		}

		$results = [];
		foreach (array_chunk($queries, $this->maxConnections, preserve_keys: true) as $chunk) {
			$results += $this->executeChunk($connection, $chunk);
		}

		$ordered = [];
		foreach ($queries as $key => $_) {
			$ordered[$key] = $results[$key];
		}
		return $ordered;
	}


	/**
	 * Closes the extra connections; they are reopened when needed again. Call it when a request ends in a long-running
	 * worker, as with the main connection. The main connection is left untouched.
	 */
	public function disconnect(): void
	{
		foreach ($this->extraConnections as [, $pool]) {
			foreach ($pool as $extra) {
				try {
					$extra->disconnect();
				} catch (Throwable) {
					// the connection is being dropped anyway
				}
			}
		}
	}


	/**
	 * DBAL connections are cyclic, so they would otherwise stay open until the cycle collector runs.
	 */
	public function __destruct()
	{
		$this->disconnect();
	}


	/**
	 * @param array<array-key, AsyncQuery> $queries
	 * @return array<array-key, list<array<string, mixed>>>
	 */
	private function executeChunk(IAsyncConnection $main, array $queries): array
	{
		$connections = [$main, ...$this->getExtraConnections($main, count($queries) - 1)];

		/** @var array<array-key, array{IAsyncConnection, PendingResult}> $sent */
		$sent = [];
		$i = 0;
		try {
			foreach ($queries as $key => $query) {
				$target = $connections[$i++];
				$sent[$key] = [$target, $target->queryArgsAsync($query->sql, $query->args)];
			}
		} catch (Throwable $e) {
			self::collectAll($sent); // e.g. an invalid query: the already sent ones must not stay unread
			throw $e;
		}

		$results = [];
		$failure = null;
		foreach ($sent as $key => [$target, $pendingResult]) {
			try {
				$results[$key] = self::fetchRows($pendingResult->getResult());
			} catch (Throwable $e) {
				$failure ??= $e; // the remaining results are still collected
				if ($target !== $main) {
					$target->disconnect(); // the extra connection may be broken; it is reconnected on its next use
				}
			}
		}
		if ($failure !== null) {
			throw $failure;
		}
		return $results;
	}


	/**
	 * @param array<array-key, array{IAsyncConnection, PendingResult}> $sent
	 */
	private static function collectAll(array $sent): void
	{
		foreach ($sent as [, $pendingResult]) {
			try {
				$pendingResult->getResult();
			} catch (Throwable) {
			}
		}
	}


	/**
	 * @return list<Connection>
	 */
	private function getExtraConnections(IAsyncConnection $main, int $count): array
	{
		$config = $main->getConfig();
		[$poolConfig, $pool] = $this->extraConnections[$main] ?? [$config, []];
		if ($poolConfig !== $config) { // e.g. Connection::reconnectWithConfig()
			foreach ($pool as $extra) {
				$extra->disconnect();
			}
			$pool = [];
		}

		foreach (array_slice($pool, 0, $count) as $extra) {
			self::connect($extra); // no-op unless disconnected after a failure
		}
		while (count($pool) < $count) {
			$extra = new Connection($this->extraConnectionConfig + $config);
			self::connect($extra);
			$pool[] = $extra; // only connections set up successfully are kept
		}
		$this->extraConnections[$main] = [$config, $pool];
		return array_slice($pool, 0, $count);
	}


	private static function connect(Connection $connection): void
	{
		try {
			$connection->connect();
		} catch (Throwable $e) {
			$connection->getDriver()->disconnect(); // the setup may fail after the session was opened
			throw $e;
		}
	}


	/**
	 * @return list<array<string, mixed>>
	 */
	private static function fetchRows(Result $result): array
	{
		$rows = [];
		while (($row = $result->fetch()) !== null) {
			$rows[] = $row->toArray();
		}
		return $rows;
	}
}
