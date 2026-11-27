<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\DriverException;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Drivers\Mysqli\MysqliDriver;
use Nextras\Dbal\Drivers\Pgsql\PgsqlDriver;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Async\AsyncQuery;
use Nextras\Orm\Async\CollectionFetcher;
use Nextras\Orm\Async\NativeAsyncQueryExecutor;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;
use function array_map;
use function microtime;
use function restore_error_handler;
use function set_error_handler;

require_once __DIR__ . '/../../../bootstrap.php';


class CollectionAsyncNativeTest extends DataTestCase
{
	private IConnection $connection;


	protected function setUp()
	{
		parent::setUp();
		if ($this->section === Helper::SECTION_ARRAY) Environment::skip('DBAL only.');
		$this->connection = $this->container->getByType(IConnection::class);
		$driver = $this->connection->getDriver();
		if (!$driver instanceof MysqliDriver && !$driver instanceof PgsqlDriver) {
			Environment::skip('Native async is implemented for mysqli & pgsql drivers.');
		}
	}


	public function testFetchesLikeSequential(): void
	{
		$filtered = $this->orm->books->findBy(['author' => 1])->orderBy('id');
		['items' => $items, 'total' => $total, 'authors' => $authors] = (new CollectionFetcher(new NativeAsyncQueryExecutor()))
			->fetchAll([
				'items' => $filtered->limitBy(1),
				'total' => CollectionFetcher::count($filtered),
				'authors' => $this->orm->authors->findAll()->orderBy('id'),
			]);

		Assert::same(2, $total->get());
		$queries = $this->getQueries(function () use ($items, $authors): void {
			Assert::same([1], array_map(fn($book) => $book->id, $items->fetchAll()));
			Assert::same(['Writer 1', 'Writer 2'], array_map(fn($author) => $author->name, $authors->fetchAll()));
			Assert::type(\DateTimeImmutable::class, $items->fetchChecked()->publishedAt); // normalized like DBAL does
		});
		Assert::same([], $queries ?? []);
	}


	public function testQueriesRunConcurrently(): void
	{
		$executor = new NativeAsyncQueryExecutor();
		$queries = [];
		for ($i = 0; $i < 3; $i++) {
			$queries["q$i"] = new AsyncQuery('SELECT ' . $this->sleepSql(0.5) . ' AS s, %i AS i', [$i]);
		}

		$executor->executeAll($this->connection, ['warm' => $queries['q0'], 'up' => $queries['q1']]); // opens extra connections

		$start = microtime(true);
		$rows = $executor->executeAll($this->connection, $queries);
		$elapsed = microtime(true) - $start;

		Assert::same(['q0', 'q1', 'q2'], array_keys($rows));
		Assert::same([0, 1, 2], array_map(fn($r) => $r[0]['i'], array_values($rows)));
		Assert::true($elapsed < 1.0, "Expected concurrent execution (sequential takes 1.5 s), took {$elapsed}s");
	}


	public function testMoreQueriesThanConnections(): void
	{
		$executor = new NativeAsyncQueryExecutor(maxConnections: 2);
		$queries = [];
		for ($i = 4; $i >= 0; $i--) {
			$queries[$i] = new AsyncQuery('SELECT %i AS i', [$i]);
		}
		$rows = $executor->executeAll($this->connection, $queries);
		Assert::same([4, 3, 2, 1, 0], array_keys($rows));
		Assert::same([4, 3, 2, 1, 0], array_map(fn($r) => $r[0]['i'], array_values($rows)));
	}


	public function testFailingQueryThrowsDbalExceptionAndConnectionsStayUsable(): void
	{
		$executor = new NativeAsyncQueryExecutor();
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
	}


	public function testInvalidQueryLeavesNoQueryUnread(): void
	{
		$executor = new NativeAsyncQueryExecutor();
		Assert::exception(fn() => $executor->executeAll($this->connection, [
			new AsyncQuery('SELECT %i AS i', [1]),
			new AsyncQuery('SELECT %i AS i'), // missing argument, fails while building the SQL
		]), \Throwable::class);

		Assert::same(5, $this->connection->query('SELECT %i AS i', 5)->fetchField());
		$rows = $executor->executeAll($this->connection, [new AsyncQuery('SELECT 1 AS i'), new AsyncQuery('SELECT 2 AS i')]);
		Assert::same([1, 2], array_map(fn($r) => $r[0]['i'], $rows));
	}


	public function testRecoversFromLostExtraConnection(): void
	{
		$executor = new NativeAsyncQueryExecutor(maxConnections: 2);
		$sessionId = $this->connection->getDriver() instanceof PgsqlDriver ? 'pg_backend_pid()' : 'CONNECTION_ID()';
		$rows = $executor->executeAll($this->connection, [
			new AsyncQuery("SELECT $sessionId AS id"),
			new AsyncQuery("SELECT $sessionId AS id"),
		]);
		$extraId = $rows[1][0]['id'];
		Assert::notSame($rows[0][0]['id'], $extraId);

		if ($this->connection->getDriver() instanceof PgsqlDriver) {
			$this->connection->query('SELECT pg_terminate_backend(%i)', $extraId);
			for (
				$i = 0;
				$i < 100 && $this->connection->query('SELECT 1 FROM pg_stat_activity WHERE pid = %i', $extraId)->fetch() !== null;
				$i++
			) {
				usleep(10_000); // terminating is asynchronous
			}
		} else {
			$this->connection->query('KILL %i', $extraId);
		}

		// the lost connection may surface once; DBAL's pgsql driver also emits a notice for it
		set_error_handler(static fn(): bool => true);
		try {
			$executor->executeAll($this->connection, [new AsyncQuery('SELECT 1 AS i'), new AsyncQuery('SELECT 2 AS i')]);
		} catch (DriverException) {
		} finally {
			restore_error_handler();
		}

		$rows = $executor->executeAll($this->connection, [new AsyncQuery('SELECT 1 AS i'), new AsyncQuery('SELECT 2 AS i')]);
		Assert::same([1, 2], array_map(fn($r) => $r[0]['i'], $rows));
	}


	public function testDriverInstanceInConfigRunsOnMainConnection(): void
	{
		$config = $this->connection->getConfig();
		$driverClass = $this->connection->getDriver()::class;
		$connection = new Connection(['driver' => new $driverClass()] + $config);
		$sessionId = $this->connection->getDriver() instanceof PgsqlDriver ? 'pg_backend_pid()' : 'CONNECTION_ID()';

		$rows = (new NativeAsyncQueryExecutor())->executeAll($connection, [
			new AsyncQuery("SELECT $sessionId AS id"),
			new AsyncQuery("SELECT $sessionId AS id"),
		]);
		Assert::same($rows[0][0]['id'], $rows[1][0]['id']); // the driver instance cannot be shared by extra connections
		$connection->disconnect();
	}


	public function testFallsBackToMainConnectionInsideTransaction(): void
	{
		$this->connection->beginTransaction();
		try {
			$this->connection->query('UPDATE books SET title = %s WHERE id IN %i[]', 'uncommitted', [1, 2]);
			[$a, $b] = (new CollectionFetcher(new NativeAsyncQueryExecutor()))->fetchAll([
				$this->orm->books->findBy(['id' => 1]),
				$this->orm->books->findBy(['id' => 2]),
			]);
			Assert::same('uncommitted', $a->fetchChecked()->title); // an extra connection would not see it
			Assert::same('uncommitted', $b->fetchChecked()->title); // the second slot would use another connection
		} finally {
			$this->connection->rollbackTransaction();
		}
	}


	private function sleepSql(float $seconds): string
	{
		return $this->connection->getDriver() instanceof PgsqlDriver ? "pg_sleep($seconds)::text" : "SLEEP($seconds)";
	}
}


$test = new CollectionAsyncNativeTest();
$test->run();
