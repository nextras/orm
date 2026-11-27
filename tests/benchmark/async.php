<?php declare(strict_types = 1);

/**
 * Async fetch benchmark: how much would running the SQL of several collections in parallel (Swoole coroutines)
 * save, compared to the serial hydration cost that remains?
 *
 * Uses the integration-test model (books, authors, tags) and seeds N rows into each table.
 * Requires ext-swoole; the whole run happens inside a coroutine scheduler with SWOOLE_HOOK_ALL,
 * so sequential and parallel numbers carry the same hook overhead.
 *
 *   php tests/benchmark/async.php --driver=mysqli|pdo_mysql|pdo_pgsql --host=mysql [--port=] [--n=500,1000] [--reps=9]
 *
 * Phases (per N, three collections of N entities each):
 *   seq.e2e      $collection->fetchAll() x3, as today
 *   seq.query    the three SELECTs, rows fetched + normalized by DBAL, no hydration
 *   hydrate      DbalMapper::hydrateEntity() for the pre-fetched rows, empty identity map
 *   par.query    the three SELECTs in 3 coroutines on 3 pooled connections
 *   par.e2e      par.query + serial hydration (what CollectionFetcher would do)
 */

namespace NextrasBench;


use Nette\Bootstrap\Configurator;
use Nextras\Dbal\Connection;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use NextrasTests\Orm\TestAppModel;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use Swoole\Runtime;
use function Swoole\Coroutine\run;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/async-seed.php';

define('TEMP_DIR', sys_get_temp_dir() . '/nextras-orm-bench');
date_default_timezone_set('Europe/Prague');

$opts = getopt('', ['driver:', 'host:', 'port:', 'n:', 'reps:', 'nohook']);
$driver = $opts['driver'] ?? 'mysqli';
$isPg = str_contains($driver, 'pgsql');
$db = [
	'driver' => $driver,
	'host' => $opts['host'] ?? '127.0.0.1',
	'port' => (int) ($opts['port'] ?? ($isPg ? 5432 : 3306)),
	'database' => 'nextras_orm_test',
	'username' => $isPg ? 'postgres' : 'root',
	'password' => $isPg ? 'postgres' : 'root',
];
$sizes = array_map('intval', explode(',', $opts['n'] ?? '500,1000'));
$reps = (int) ($opts['reps'] ?? 9);

Runtime::enableCoroutine(isset($opts['nohook']) ? 0 : SWOOLE_HOOK_ALL);

run(function () use ($db, $sizes, $reps, $isPg): void {
	$configurator = new Configurator();
	$configurator->setTempDirectory(TEMP_DIR);
	$configurator->addStaticParameters([
		'container' => ['class' => 'Bench' . md5(serialize($db)) . 'Container'],
		'db' => $db,
		'autowired1' => true,
		'autowired2' => false,
	]);
	$configurator->addConfig(__DIR__ . '/../config.dbal.neon');
	$container = $configurator->createContainer();
	$orm = $container->getByType(TestAppModel::class);
	$connection = $container->getByType(IConnection::class);
	assert($connection instanceof Connection);

	seedAsyncDatabase($connection, max($sizes), $isPg);

	$pool = [new Connection($db), new Connection($db), new Connection($db)];
	foreach ($pool as $c) $c->connect();

	$results = [];
	foreach ($sizes as $n) {
		$orm->clear();
		/** @var list<array{DbalMapper<IEntity>, QueryBuilder}> $sources */
		$sources = [];
		foreach ([$orm->books, $orm->authors, $orm->tags] as $repo) {
			$collection = $repo->findAll()->orderBy('id')->limitBy($n);
			assert($collection instanceof DbalCollection);
			$mapper = $repo->getMapper();
			assert($mapper instanceof DbalMapper);
			$sources[] = [$mapper, clone $collection->getQueryBuilder()];
		}

		$fetchRows = function (IConnection $conn, QueryBuilder $qb): array {
			$result = $conn->queryByQueryBuilder($qb);
			$rows = [];
			while (($row = $result->fetch()) !== null) $rows[] = $row->toArray();
			return $rows;
		};
		$parallelRows = function () use ($pool, $sources, $fetchRows): array {
			$out = [];
			$wg = new WaitGroup();
			foreach ($sources as $i => [, $qb]) {
				$wg->add();
				Coroutine::create(function () use ($i, $qb, $pool, $fetchRows, &$out, $wg): void {
					$out[$i] = $fetchRows($pool[$i], $qb);
					$wg->done();
				});
			}
			$wg->wait();
			ksort($out);
			return $out;
		};
		$hydrate = function (array $rowSets) use ($orm, $sources): int {
			$orm->clear();
			$count = 0;
			foreach ($rowSets as $i => $rows) {
				foreach ($rows as $row) {
					$sources[$i][0]->hydrateEntity($row) !== null && $count++;
				}
			}
			return $count;
		};

		$prefetched = array_map(fn($s) => $fetchRows($connection, $s[1]), $sources);
		$expected = 3 * $n;

		$phases = [
			'seq.e2e' => function () use ($orm, $n, $expected): void {
				$orm->clear();
				$count = 0;
				foreach ([$orm->books, $orm->authors, $orm->tags] as $repo) {
					$count += count($repo->findAll()->orderBy('id')->limitBy($n)->fetchAll());
				}
				assert($count === $expected);
			},
			'seq.query' => function () use ($connection, $sources, $fetchRows): void {
				foreach ($sources as [, $qb]) $fetchRows($connection, $qb);
			},
			'hydrate' => function () use ($hydrate, $prefetched, $expected): void {
				$c = $hydrate($prefetched);
				assert($c === $expected);
			},
			'par.query' => function () use ($parallelRows): void {
				$parallelRows();
			},
			'par.e2e' => function () use ($parallelRows, $hydrate, $expected): void {
				$c = $hydrate($parallelRows());
				assert($c === $expected);
			},
		];

		foreach ($phases as $name => $fn) {
			$fn(); // warm-up
			$times = [];
			for ($r = 0; $r < $reps; $r++) {
				gc_collect_cycles();
				$t = hrtime(true);
				$fn();
				$times[] = (hrtime(true) - $t) / 1e6;
			}
			sort($times);
			$results[$n][$name] = ['min' => $times[0], 'med' => $times[intdiv(count($times), 2)]];
		}
	}

	printf("driver=%s host=%s reps=%d PHP %s swoole %s\n", $db['driver'], $db['host'], $reps, PHP_VERSION, SWOOLE_VERSION);
	foreach ($results as $n => $phases) {
		printf("\n3 collections x %d entities (books, authors, tags)\n", $n);
		printf("  %-10s %9s %9s\n", 'phase', 'min ms', 'med ms');
		foreach ($phases as $name => $t) printf("  %-10s %9.2f %9.2f\n", $name, $t['min'], $t['med']);
		$seq = $phases['seq.e2e']['med'];
		$par = $phases['par.e2e']['med'];
		printf(
			"  speedup e2e (median): %.2fx  (%.2f ms saved, %.0f%% of seq.e2e is hydration)\n",
			$seq / $par,
			$seq - $par,
			100 * $phases['hydrate']['med'] / $seq,
		);
	}
});
