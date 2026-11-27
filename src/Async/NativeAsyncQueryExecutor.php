<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use mysqli;
use mysqli_result;
use mysqli_sql_exception;
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
use WeakMap;
use function array_chunk;
use function array_slice;
use function count;
use function is_string;
use function pg_get_result;
use function pg_result_error_field;
use function pg_send_query;
use const MYSQLI_ASYNC, PGSQL_DIAG_SQLSTATE;


/**
 * Runs queries concurrently without an event loop, so it works on PHP-FPM as well: every query is sent without
 * waiting for its result (`mysqli::query(MYSQLI_ASYNC)` / `pg_send_query()`) on its own connection, then the results
 * are collected in order. The database server executes the queries in parallel; fetching rows stays serial.
 *
 * The first query uses the main connection, the others use extra connections opened lazily with the main
 * connection's config (optionally overridden by $extraConnectionConfig, e.g. `['host' => 'p:db']` for persistent
 * mysqli connections) and kept for the lifetime of this executor.
 *
 * Supported drivers: mysqli, pgsql; for others, inside a transaction (extra connections would not see uncommitted
 * data) and for a single query, the fallback executor is used. Queries sent this way are not reported to the
 * connection's loggers. When a query fails, all sent queries are collected first, then the failing query is re-run
 * on its connection through {@see Connection::query()} to raise the regular DBAL exception.
 */
final class NativeAsyncQueryExecutor implements IQueryExecutor
{
	/** @var WeakMap<Connection, list<Connection>> */
	private WeakMap $extraConnections;

	/** @var WeakMap<Connection, SqlProcessor> */
	private WeakMap $sqlProcessors;

	/** @var WeakMap<Connection, MysqliResultNormalizerFactory|PgsqlResultNormalizerFactory> */
	private WeakMap $normalizerFactories;


	public function __construct(
		/** Maximum number of connections (including the main one) used at once. */
		private readonly int $maxConnections = 4,
		/** @var array<string, mixed> */
		private readonly array $extraConnectionConfig = [],
		private readonly IQueryExecutor $fallback = new SequentialQueryExecutor(),
	)
	{
		if ($maxConnections < 1) {
			throw new InvalidArgumentException('At least one connection is required.');
		}
		$this->extraConnections = new WeakMap();
		$this->sqlProcessors = new WeakMap();
		$this->normalizerFactories = new WeakMap();
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
			return $this->fallback->executeAll($connection, $queries);
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
		$driver = $connection->getDriver();
		return $driver instanceof MysqliDriver || $driver instanceof PgsqlDriver;
	}


	/**
	 * @param array<array-key, AsyncQuery> $queries
	 * @return array<array-key, list<array<string, mixed>>>
	 */
	private function executeChunk(Connection $main, array $queries): array
	{
		$sqlProcessor = $this->sqlProcessors[$main] ??= $this->createSqlProcessor($main);
		$connections = [$main, ...$this->getExtraConnections($main, count($queries) - 1)];

		/** @var array<array-key, array{Connection, string, bool}> $sent */
		$sent = [];
		$i = 0;
		foreach ($queries as $key => $query) {
			$target = $connections[$i++];
			$sql = $sqlProcessor->process([$query->sql, ...$query->args]);
			$sent[$key] = [$target, $sql, $this->send($target, $sql)];
		}

		$results = [];
		$failed = [];
		foreach ($sent as $key => [$target, $sql, $isSent]) {
			$result = $isSent ? $this->reap($target) : null;
			if ($result === null) {
				$failed[$key] = [$target, $sql];
			} else {
				$results[$key] = self::fetchRows($result);
			}
		}

		foreach ($failed as $key => [$target, $sql]) {
			// every connection is idle now; re-running yields the regular DBAL exception (or the result if transient)
			$results[$key] = self::fetchRows($target->query('%raw', $sql));
		}

		return $results;
	}


	/**
	 * @return list<Connection>
	 */
	private function getExtraConnections(Connection $main, int $count): array
	{
		$pool = $this->extraConnections[$main] ?? [];
		while (count($pool) < $count) {
			$extra = new Connection($this->extraConnectionConfig + $main->getConfig());
			$extra->connect();
			$pool[] = $extra;
		}
		$this->extraConnections[$main] = $pool;
		return array_slice($pool, 0, $count);
	}


	private function createSqlProcessor(Connection $connection): SqlProcessor
	{
		$factory = $connection->getConfig()['sqlProcessorFactory'] ?? null;
		return $factory instanceof ISqlProcessorFactory
			? $factory->create($connection)
			: new SqlProcessor($connection->getPlatform());
	}


	private function send(Connection $connection, string $sql): bool
	{
		$handle = $connection->getDriver()->getResourceHandle();
		if ($handle instanceof mysqli) {
			try {
				return $handle->query($sql, MYSQLI_ASYNC) !== false;
			} catch (mysqli_sql_exception) {
				return false;
			}
		} elseif ($handle instanceof PgConnection) {
			return pg_send_query($handle, $sql) !== false;
		}
		return false;
	}


	private function reap(Connection $connection): Result|null
	{
		$driver = $connection->getDriver();
		$handle = $driver->getResourceHandle();

		if ($driver instanceof MysqliDriver && $handle instanceof mysqli) {
			try {
				$result = $handle->reap_async_query();
			} catch (mysqli_sql_exception) {
				return null;
			}
			if ($result === false) {
				return null;
			} elseif (!$result instanceof mysqli_result) {
				return new Result(new MysqliEmptyResultAdapter());
			}
			$factory = $this->normalizerFactories[$connection] ??= new MysqliResultNormalizerFactory($driver);
			assert($factory instanceof MysqliResultNormalizerFactory);
			return new Result(new MysqliResultAdapter($result, $factory));

		} elseif ($handle instanceof PgConnection) {
			$result = pg_get_result($handle);
			while (pg_get_result($handle) !== false) {
				// drains the connection so that it accepts the next query
			}
			if ($result === false || is_string(pg_result_error_field($result, PGSQL_DIAG_SQLSTATE))) {
				return null;
			}
			$factory = $this->normalizerFactories[$connection] ??= new PgsqlResultNormalizerFactory();
			assert($factory instanceof PgsqlResultNormalizerFactory);
			return new Result(new PgsqlResultAdapter($result, $factory));
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
