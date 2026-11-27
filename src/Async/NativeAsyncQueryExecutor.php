<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use mysqli;
use mysqli_result;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Mysqli\MysqliDriver;
use Nextras\Dbal\Drivers\Mysqli\MysqliEmptyResultAdapter;
use Nextras\Dbal\Drivers\Mysqli\MysqliResultAdapter;
use Nextras\Dbal\Drivers\Mysqli\MysqliResultNormalizerFactory;
use Nextras\Dbal\Drivers\Pgsql\PgsqlDriver;
use Nextras\Dbal\Drivers\Pgsql\PgsqlResultAdapter;
use Nextras\Dbal\Drivers\Pgsql\PgsqlResultNormalizerFactory;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ISqlProcessorFactory;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\SqlProcessor;
use Nextras\Orm\Exception\InvalidArgumentException;
use PgSql\Connection as PgConnection;
use Throwable;
use WeakMap;
use function array_chunk;
use function array_slice;
use function count;
use function defined;
use function in_array;
use function is_string;
use function pg_get_result;
use function pg_result_error_field;
use function pg_result_status;
use function pg_send_query;
use const PGSQL_COPY_IN, PGSQL_COPY_OUT, PGSQL_DIAG_SQLSTATE;


/**
 * Runs queries concurrently without an event loop, so it works on PHP-FPM as well: every query is sent without
 * waiting for its result (`mysqli::query(MYSQLI_ASYNC)` / `pg_send_query()`) on its own connection, then the results
 * are collected in order. The database server executes the queries in parallel; fetching rows stays serial.
 *
 * The first query uses the main connection, the others use extra connections opened lazily with the main
 * connection's config (optionally overridden by $extraConnectionConfig, e.g. `['host' => 'p:db']` for persistent
 * mysqli connections) and kept for the lifetime of this executor; they are replaced when the main connection's
 * config changes. Each query is formatted by the connection it is sent to. Queries must be single statements
 * returning one result set, i.e. plain SELECTs.
 *
 * Supported drivers: mysqli (with mysqlnd) and pgsql. The queries run sequentially on the main connection for other
 * drivers, for a driver instance passed in the config (it cannot be shared by several connections), inside
 * a transaction started through DBAL (extra connections would not see uncommitted data) and for a single query.
 * Queries sent concurrently are not reported to the connection's loggers. When a query fails, all sent queries are
 * collected first, then the failing query is re-run on its connection through {@see Connection::query()} to raise
 * the regular DBAL exception.
 */
final class NativeAsyncQueryExecutor implements IQueryExecutor
{
	/** @var WeakMap<Connection, array{array<string, mixed>, list<Connection>}> main connection => its config and extra connections */
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
			|| !$connection instanceof Connection
			|| !self::isSupported($connection)
			|| $connection->getTransactionNestedIndex() > 0
		) {
			return (new SequentialQueryExecutor())->executeAll($connection, $queries);
		}

		$connection->connect();
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


	private static function isSupported(Connection $connection): bool
	{
		if (!is_string($connection->getConfig()['driver'] ?? null)) {
			return false; // a driver instance cannot be shared by the extra connections
		}
		$driver = $connection->getDriver();
		return ($driver instanceof MysqliDriver && defined('MYSQLI_ASYNC')) // MYSQLI_ASYNC requires mysqlnd
			|| $driver instanceof PgsqlDriver;
	}


	/**
	 * @param array<array-key, AsyncQuery> $queries
	 * @return array<array-key, list<array<string, mixed>>>
	 */
	private function executeChunk(Connection $main, array $queries): array
	{
		$connections = [$main, ...$this->getExtraConnections($main, count($queries) - 1)];

		// Everything that may throw before sending is done first, so that no query is left unread on a connection.
		/** @var array<array-key, array{Connection, string}> $prepared */
		$prepared = [];
		$i = 0;
		foreach ($queries as $key => $query) {
			$target = $connections[$i++];
			$prepared[$key] = [$target, self::createSqlProcessor($target)->process([$query->sql, ...$query->args])];
		}

		$isSent = [];
		foreach ($prepared as $key => [$target, $sql]) {
			$isSent[$key] = self::send($target, $sql);
		}

		/** @var array<array-key, Result|null> $reaped */
		$reaped = [];
		foreach ($prepared as $key => [$target]) {
			$reaped[$key] = $isSent[$key] ? self::reap($target) : null;
		}

		// All connections are idle now.
		$results = [];
		foreach ($reaped as $key => $result) {
			unset($reaped[$key]); // releases each buffered result once converted
			if ($result !== null) {
				$results[$key] = self::fetchRows($result);
				continue;
			}

			// Re-running yields the regular DBAL exception (or the result, if the failure was transient).
			[$target, $sql] = $prepared[$key];
			try {
				$results[$key] = self::fetchRows($target->query('%raw', $sql));
			} catch (Throwable $e) {
				if ($target !== $main) {
					$target->disconnect(); // the extra connection may be broken; it is reconnected on its next use
				}
				throw $e;
			}
		}

		return $results;
	}


	/**
	 * @return list<Connection>
	 */
	private function getExtraConnections(Connection $main, int $count): array
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


	private static function createSqlProcessor(Connection $connection): SqlProcessor
	{
		$factory = $connection->getConfig()['sqlProcessorFactory'] ?? null;
		return $factory instanceof ISqlProcessorFactory
			? $factory->create($connection)
			: new SqlProcessor($connection->getPlatform());
	}


	private static function send(Connection $connection, string $sql): bool
	{
		try {
			$handle = $connection->getDriver()->getResourceHandle();
			if ($handle instanceof mysqli) {
				return $handle->query($sql, MYSQLI_ASYNC) !== false;
			} elseif ($handle instanceof PgConnection) {
				return @pg_send_query($handle, $sql) !== false; // dresscode:ignore mistakes.errorSuppression -- the failure is re-raised by the synchronous re-run
			}
		} catch (Throwable) {
		}
		return false;
	}


	/**
	 * Reads the result and leaves the connection ready for the next query; null on failure.
	 */
	private static function reap(Connection $connection): Result|null
	{
		try {
			$driver = $connection->getDriver();
			$handle = $driver->getResourceHandle();

			if ($driver instanceof MysqliDriver && $handle instanceof mysqli) {
				$result = $handle->reap_async_query();
				while ($handle->more_results() && $handle->next_result()) { // e.g. a CALL; only the first result is used
					$handle->store_result();
				}
				if ($result === false) {
					return null;
				} elseif (!$result instanceof mysqli_result) {
					return new Result(new MysqliEmptyResultAdapter());
				}
				return new Result(new MysqliResultAdapter($result, new MysqliResultNormalizerFactory($driver)));

			} elseif ($handle instanceof PgConnection) {
				$result = pg_get_result($handle);
				for ($next = $result; $next !== false; $next = pg_get_result($handle)) {
					if (in_array(pg_result_status($next), [PGSQL_COPY_IN, PGSQL_COPY_OUT], true)) {
						$connection->disconnect(); // a COPY cannot be finished here; the connection is unusable
						return null;
					}
				}
				if ($result === false || is_string(pg_result_error_field($result, PGSQL_DIAG_SQLSTATE))) {
					return null;
				}
				return new Result(new PgsqlResultAdapter($result, new PgsqlResultNormalizerFactory()));
			}
		} catch (Throwable) {
		}
		return null;
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
