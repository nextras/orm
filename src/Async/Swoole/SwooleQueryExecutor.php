<?php declare(strict_types = 1);

namespace Nextras\Orm\Async\Swoole;


use Nextras\Dbal\IConnection;
use Nextras\Orm\Async\IQueryExecutor;
use Nextras\Orm\Async\SequentialQueryExecutor;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Throwable;
use WeakMap;
use function count;


/**
 * Runs every query in its own coroutine on a pooled connection created from the main connection's config.
 *
 * Real concurrency requires Swoole runtime hooks (`Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL)`, enabled by
 * default in Swoole servers) and a hookable driver (mysqli, pdo_mysql, pdo_pgsql); native ext-pgsql blocks.
 * Falls back to the fallback executor outside a coroutine, inside a transaction (pooled connections would not see
 * uncommitted data) and for a single query. Queries are reported to the pooled connections' loggers only.
 */
final class SwooleQueryExecutor implements IQueryExecutor
{
	/** @var WeakMap<IConnection, SwooleConnectionPool> */
	private WeakMap $pools;


	public function __construct(
		/** Maximum number of pooled connections per main connection. */
		private readonly int $poolSize = 8,
		private readonly IQueryExecutor $fallback = new SequentialQueryExecutor(),
	)
	{
		$this->pools = new WeakMap();
	}


	public function executeAll(IConnection $connection, array $queries): array
	{
		if (
			count($queries) < 2
			|| Coroutine::getCid() < 0
			|| $connection->getTransactionNestedIndex() > 0
		) {
			return $this->fallback->executeAll($connection, $queries);
		}

		$pool = $this->pools[$connection] ??= new SwooleConnectionPool($connection->getConfig(), $this->poolSize);
		$results = [];
		$errors = [];
		$waitGroup = new WaitGroup();

		foreach ($queries as $key => $query) {
			$waitGroup->add();
			Coroutine::create(function () use ($pool, $query, $key, &$results, &$errors, $waitGroup): void {
				$pooled = $pool->acquire();
				try {
					$result = $pooled->queryArgs($query->sql, $query->args);
					$rows = [];
					while (($row = $result->fetch()) !== null) {
						$rows[] = $row->toArray();
					}
					$results[$key] = $rows;
				} catch (Throwable $e) {
					$errors[$key] = $e;
					$pooled->disconnect(); // reconnects lazily on the next query
				} finally {
					$pool->release($pooled);
					$waitGroup->done();
				}
			});
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
