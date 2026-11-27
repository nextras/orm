<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Mysqli\MysqliDriver;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Async\AsyncQuery;
use Nextras\Orm\Async\CollectionFetcher;
use Nextras\Orm\Async\Swoole\SwooleQueryExecutor;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Swoole\Runtime;
use Tester\Assert;
use Tester\Environment;
use Throwable;
use function array_map;
use function extension_loaded;
use function microtime;
use function Swoole\Coroutine\run;
use const SWOOLE_HOOK_ALL;

require_once __DIR__ . '/../../../bootstrap.php';


class CollectionAsyncSwooleTest extends DataTestCase
{
	private IConnection $connection;


	protected function setUp()
	{
		if (!extension_loaded('swoole')) Environment::skip('ext-swoole is required.');
		parent::setUp();
		if ($this->section === Helper::SECTION_ARRAY) Environment::skip('DBAL only.');
		$this->connection = $this->container->getByType(IConnection::class);
	}


	public function testFetchesLikeSequential(): void
	{
		$this->inCoroutine(function (): void {
			$filtered = $this->orm->books->findBy(['author' => 1])->orderBy('id');
			['items' => $items, 'total' => $total, 'authors' => $authors] = (new CollectionFetcher(new SwooleQueryExecutor(poolSize: 2)))
				->fetchAll([
					'items' => $filtered->limitBy(1),
					'total' => CollectionFetcher::count($filtered),
					'authors' => $this->orm->authors->findAll()->orderBy('id'),
				]);

			Assert::same(2, $total->get());
			Assert::same([1], array_map(fn($book) => $book->id, $items->fetchAll()));
			Assert::same(['Writer 1', 'Writer 2'], array_map(fn($author) => $author->name, $authors->fetchAll()));
		});
	}


	public function testQueriesRunConcurrently(): void
	{
		if (!$this->connection->getDriver() instanceof MysqliDriver) {
			Environment::skip('Concurrency needs a hookable driver; ext-pgsql is not hooked by Swoole.');
		}

		$this->inCoroutine(function (): void {
			$executor = new SwooleQueryExecutor(poolSize: 3);
			$queries = [];
			for ($i = 0; $i < 3; $i++) {
				$queries[$i] = new AsyncQuery('SELECT SLEEP(0.3) AS s, %i AS i', [$i]);
			}

			$start = microtime(true);
			$rows = $executor->executeAll($this->connection, $queries);
			$elapsed = microtime(true) - $start;

			Assert::same([0, 1, 2], array_map(fn($r) => $r[0]['i'], $rows));
			Assert::true($elapsed < 0.6, "Expected concurrent execution, took {$elapsed}s");
		});
	}


	public function testFailingQueryThrowsAndPoolStaysUsable(): void
	{
		$this->inCoroutine(function (): void {
			$executor = new SwooleQueryExecutor(poolSize: 2);
			Assert::exception(fn() => $executor->executeAll($this->connection, [
				new AsyncQuery('SELECT %i AS i', [1]),
				new AsyncQuery('SELECT * FROM non_existing_table'),
				new AsyncQuery('SELECT %i AS i', [3]),
			]), QueryException::class);

			$rows = $executor->executeAll($this->connection, [
				new AsyncQuery('SELECT %i AS i', [1]),
				new AsyncQuery('SELECT %i AS i', [2]),
				new AsyncQuery('SELECT %i AS i', [3]),
			]);
			Assert::same([1, 2, 3], array_map(fn($r) => $r[0]['i'], $rows));
		});
	}


	public function testFallsBackToMainConnectionInsideTransaction(): void
	{
		$this->inCoroutine(function (): void {
			$this->connection->beginTransaction();
			try {
				$this->connection->query('UPDATE books SET title = %s WHERE id = 1', 'uncommitted');
				[$a, $b] = (new CollectionFetcher(new SwooleQueryExecutor()))->fetchAll([
					$this->orm->books->findBy(['id' => 1]),
					$this->orm->books->findBy(['id' => 2]),
				]);
				Assert::same('uncommitted', $a->fetchChecked()->title); // a pooled connection would not see it
				Assert::same(2, $b->fetchChecked()->id);
			} finally {
				$this->connection->rollbackTransaction();
			}
		});
	}


	public function testFallsBackOutsideCoroutine(): void
	{
		$rows = (new SwooleQueryExecutor())->executeAll($this->connection, [
			new AsyncQuery('SELECT %i AS i', [1]),
			new AsyncQuery('SELECT %i AS i', [2]),
		]);
		Assert::same([1, 2], array_map(fn($r) => $r[0]['i'], $rows));
	}


	/**
	 * Runs the callback inside a Swoole scheduler with runtime hooks enabled; rethrows its failure.
	 * @param callable(): void $callback
	 */
	private function inCoroutine(callable $callback): void
	{
		Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
		$error = null;
		try {
			run(function () use ($callback, &$error): void {
				try {
					$callback();
				} catch (Throwable $e) {
					$error = $e;
				}
			});
		} finally {
			Runtime::enableCoroutine(0);
		}
		if ($error !== null) throw $error;
	}
}


$test = new CollectionAsyncSwooleTest();
$test->run();
