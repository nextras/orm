<?php declare(strict_types = 1);

namespace Nextras\Orm\Async\Swoole;


use Nextras\Dbal\IConnection;
use Nextras\Orm\Async\IQueryExecutor;
use Nextras\Orm\Async\SequentialQueryExecutor;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Throwable;
use WeakMap;
use function count;
use function is_string;


/**
 * Runs every query in its own coroutine on a pooled connection created from the main connection's config.
 *
 * Real concurrency requires Swoole runtime hooks (`Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL)`, enabled by
 * default in Swoole servers) and a hookable driver (mysqli, pdo_mysql, pdo_pgsql); native ext-pgsql blocks.
 * Runs the queries sequentially on the main connection outside a coroutine, inside a transaction started through DBAL
 * (pooled connections would not see uncommitted data), for a driver instance passed in the config and for a single
 * query. Pools are replaced when the main connection's config changes. Queries are reported to the pooled connections' loggers only.
 */
final class SwooleQueryExecutor implements IQueryExecutor
{
	/** @var WeakMap<IConnection, SwooleConnectionPool> */
	private WeakMap $pools;


	public function __construct(
		/** Maximum number of pooled connections per main connection. */
		private readonly int $poolSize = 8,
	)
	{
		if ($poolSize < 1) {
			throw new InvalidArgumentException('At least one pooled connection is required.');
		}
		$this->pools = new WeakMap();
	}


	public function executeAll(IConnection $connection, array $queries): array
	{
		$config = $connection->getConfig();
		if (
			count($queries) < 2
			|| Coroutine::getCid() < 0
			|| $connection->getTransactionNestedIndex() > 0
			|| !is_string($config['driver'] ?? null) // a driver instance cannot be shared by pooled connections
		) {
			return (new SequentialQueryExecutor())->executeAll($connection, $queries);
		}

		$pool = $this->pools[$connection] ?? null;
		if ($pool === null || $pool->config !== $config) { // e.g. Connection::reconnectWithConfig()
			$pool?->close();
			$pool = $this->pools[$connection] = new SwooleConnectionPool($config, $this->poolSize);
		}
		$results = [];
		$errors = [];
		$waitGroup = new WaitGroup();

		foreach ($queries as $key => $query) {
			$waitGroup->add();
			try {
				$created = Coroutine::create(function () use ($pool, $query, $key, &$results, &$errors, $waitGroup): void {
					$pooled = null;
					try {
						$pooled = $pool->acquire();
						$result = $pooled->queryArgs($query->sql, $query->args);
						$rows = [];
						while (($row = $result->fetch()) !== null) {
							$rows[] = $row->toArray();
						}
						$results[$key] = $rows;
					} catch (Throwable $e) {
						$errors[$key] = $e;
						$pooled?->disconnect(); // reconnects lazily on the next query
					} finally {
						if ($pooled !== null) {
							$pool->release($pooled);
						}
						$waitGroup->done();
					}
				});
			} catch (Throwable) { // e.g. the max_coroutine warning turned into an exception
				$created = false;
			}
			if ($created === false) { // e.g. the max_coroutine limit
				$errors[$key] = new InvalidStateException('Unable to create a coroutine for the query.');
				$waitGroup->done();
			}
		}

		$waitGroup->wait();

		$ordered = [];
		foreach ($queries as $key => $_) {
			if (isset($errors[$key])) {
				throw $errors[$key];
			}
			$ordered[$key] = $results[$key];
		}
		return $ordered;
	}
}
