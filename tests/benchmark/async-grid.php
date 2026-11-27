<?php declare(strict_types = 1);

/**
 * Grid-endpoint benchmark for CollectionFetcher on a plain PHP runtime (as under PHP-FPM, no Swoole):
 * one request = page of filtered books + total count of the filter + list of authors for a filter select.
 *
 *   php tests/benchmark/async-grid.php --driver=mysqli|pgsql --host=mysql [--n=1000] [--page=50] [--reps=15]
 *
 * Variants:
 *   sequential        SequentialQueryExecutor (what the code does today)
 *   native.warm       NativeAsyncQueryExecutor, extra connections already open (long-running worker, or persistent)
 *   native.cold       new NativeAsyncQueryExecutor per request: opens the extra connections each time (FPM)
 *   native.cold.p:    as native.cold, extra mysqli connections persistent via the "p:" host prefix (mysqli only)
 */

namespace NextrasBench;


use inc\model\book\GenreEnum;
use Nette\Bootstrap\Configurator;
use Nextras\Dbal\Connection;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Async\CollectionFetcher;
use Nextras\Orm\Async\IQueryExecutor;
use Nextras\Orm\Async\NativeAsyncQueryExecutor;
use Nextras\Orm\Async\SequentialQueryExecutor;
use NextrasTests\Orm\TestAppModel;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/async-seed.php';

define('TEMP_DIR', sys_get_temp_dir() . '/nextras-orm-bench');
date_default_timezone_set('Europe/Prague');

$opts = getopt('', ['driver:', 'host:', 'port:', 'n:', 'page:', 'reps:']);
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
$n = (int) ($opts['n'] ?? 1000);
$page = (int) ($opts['page'] ?? 50);
$reps = (int) ($opts['reps'] ?? 15);

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
seedAsyncDatabase($connection, $n, $isPg);

$request = function (IQueryExecutor $executor) use ($orm, $page): void {
	$orm->clear();
	$filtered = $orm->books->findBy([
		'genre' => GenreEnum::FANTASY,
		'author->bornOn>=' => new \DateTimeImmutable('1970-06-01'),
	]);
	['items' => $items, 'total' => $total, 'authors' => $authors] = (new CollectionFetcher($executor))->fetchAll([
		'items' => $filtered->orderBy('title')->limitBy($page, $page),
		'total' => CollectionFetcher::count($filtered),
		'authors' => $orm->authors->findAll()->orderBy('name')->limitBy(200),
	]);
	assert(count($items->fetchAll()) === $page && $total->get() > $page && count($authors->fetchAll()) === 200);
};

$sequential = new SequentialQueryExecutor();
$warm = new NativeAsyncQueryExecutor(maxConnections: 3);
$variants = [
	'sequential' => fn() => $request($sequential),
	'native.warm' => fn() => $request($warm),
	'native.cold' => fn() => $request(new NativeAsyncQueryExecutor(maxConnections: 3)),
];
if (!$isPg) {
	$variants['native.cold.p:'] = fn() => $request(new NativeAsyncQueryExecutor(
		maxConnections: 3,
		extraConnectionConfig: ['host' => 'p:' . $db['host']],
	));
}

$results = [];
foreach ($variants as $name => $fn) {
	$fn();
	$times = [];
	for ($r = 0; $r < $reps; $r++) {
		gc_collect_cycles();
		$t = hrtime(true);
		$fn();
		$times[] = (hrtime(true) - $t) / 1e6;
	}
	sort($times);
	$results[$name] = $times[intdiv(count($times), 2)];
}

printf("driver=%s host=%s page=%d reps=%d PHP %s\n", $db['driver'], $db['host'], $page, $reps, PHP_VERSION);
foreach ($results as $name => $median) {
	printf("  %-15s %8.2f ms  %5.2fx\n", $name, $median, $results['sequential'] / $median);
}
