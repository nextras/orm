<?php declare(strict_types = 1);

/**
 * Hydration benchmark: measures the per-entity cost of turning DB rows into entities, split by phase.
 *
 * Usage (inside the php docker image, see tests/benchmark/README in the report):
 *   php tests/benchmark/hydration.php --driver=pgsql|mysql|array [--n=1000,10000,50000] [--reps=5] [--di] [--profile]
 *
 *   --driver   section from tests/databases.ini (pgsql/mysql) or "array" for the memory mapper
 *   --n        comma separated row counts
 *   --reps     repetitions per measurement; the minimum is reported
 *   --di       attach a Nette DI based DependencyProvider (as OrmExtension does by default)
 *   --profile  additionally run the end-to-end path under excimer and print top functions by self time
 *   --json     print the result as JSON (for before/after comparisons)
 *   --phases   regex filter of measured phases (e.g. "m:1|e2e")
 *   --nogc     disable the cycle collector (to quantify GC share)
 */

namespace NextrasBench;

use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Nette\DI\Container;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Bridges\NetteDI\DependencyProvider;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Model\SimpleModelFactory;
use ReflectionClass;


require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/model.php';

$opts = getopt('', ['driver:', 'n:', 'reps:', 'di', 'profile', 'json', 'phases:', 'nogc']);
if (isset($opts['nogc'])) gc_disable();
$phaseFilter = isset($opts['phases']) ? '~' . $opts['phases'] . '~' : null;
$driver = $opts['driver'] ?? 'pgsql';
$sizes = array_map('intval', explode(',', $opts['n'] ?? '1000,10000,50000'));
$reps = (int) ($opts['reps'] ?? 5);
$useDi = isset($opts['di']);
$profile = isset($opts['profile']);
$asJson = isset($opts['json']);
$maxN = max($sizes);

const ITEM_PROPERTIES = [
	'id', 'name', 'quantity', 'price', 'isActive', 'note', 'createdAt', 'updatedAt', 'validFrom', 'status',
	'payload', 'dimensions', 'category', 'comments',
];

$cache = new Cache(new MemoryStorage());
$connection = null;
if ($driver !== 'array') {
	$config = parse_ini_file(__DIR__ . '/../databases.ini', true);
	if (!isset($config[$driver])) {
		fwrite(STDERR, "Missing [$driver] section in tests/databases.ini\n");
		exit(1);
	}
	$connection = new Connection($config[$driver]);
	seedDatabase($connection, $maxN);
}
$arrayData = $driver === 'array' ? generateArrayData($maxN) : null;


/**
 * @param array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}|null $arrayData
 */
function createModel(Cache $cache, ?Connection $connection, bool $useDi, ?array $arrayData): Model
{
	$dependencyProvider = $useDi ? new DependencyProvider(new Container()) : null;
	if ($connection !== null) {
		$coordinator = new DbalMapperCoordinator($connection);
		$repositories = [
			'items' => new BenchItemsRepository(new BenchItemsMapper($connection, $coordinator, $cache), $dependencyProvider),
			'categories' => new BenchCategoriesRepository(new BenchCategoriesMapper($connection, $coordinator, $cache), $dependencyProvider),
			'comments' => new BenchCommentsRepository(new BenchCommentsMapper($connection, $coordinator, $cache), $dependencyProvider),
		];
	} else {
		assert($arrayData !== null);
		$mappers = [new BenchArrayMapper(), new BenchArrayMapper(), new BenchArrayMapper()];
		[$mappers[0]->rows, $mappers[1]->rows, $mappers[2]->rows] = $arrayData;
		$repositories = [
			'items' => new BenchItemsRepository($mappers[0], $dependencyProvider),
			'categories' => new BenchCategoriesRepository($mappers[1], $dependencyProvider),
			'comments' => new BenchCommentsRepository($mappers[2], $dependencyProvider),
		];
	}
	$model = (new SimpleModelFactory($cache, $repositories))->create();
	if ($connection !== null) {
		// warm up conventions (db reflection) outside of measurements
		foreach ($repositories as $repository) {
			$repository->getMapper()->getConventions(); // @phpstan-ignore-line
		}
	}
	return $model;
}


function seedDatabase(Connection $connection, int $n): void
{
	$platform = $connection->getPlatform()->getName();
	$hasTable = false;
	foreach ($connection->getPlatform()->getTables() as $table) {
		if ($table->fqnName->name === 'bench_items') $hasTable = true;
	}
	if ($hasTable && (int) $connection->query('SELECT COUNT(*) FROM bench_items')->fetchField() >= $n) {
		return;
	}

	fwrite(STDERR, "[seed] creating $n rows in $platform\n");
	$connection->query('DROP TABLE IF EXISTS bench_comments');
	$connection->query('DROP TABLE IF EXISTS bench_items');
	$connection->query('DROP TABLE IF EXISTS bench_categories');
	if ($platform === 'pgsql') {
		$connection->query('CREATE TABLE bench_categories (id SERIAL PRIMARY KEY, name varchar(100) NOT NULL)');
		$connection->query('CREATE TABLE bench_items (
			id SERIAL PRIMARY KEY, name varchar(100) NOT NULL, quantity int NOT NULL, price double precision NOT NULL,
			is_active boolean NOT NULL, note text NULL, created_at timestamp NOT NULL, updated_at timestamp NULL,
			valid_from date NOT NULL, status varchar(20) NOT NULL, payload json NOT NULL,
			dimensions_width int NULL, dimensions_height int NULL,
			category_id int NOT NULL REFERENCES bench_categories (id))');
		$connection->query('CREATE TABLE bench_comments (id SERIAL PRIMARY KEY, text varchar(200) NOT NULL,
			item_id int NOT NULL REFERENCES bench_items (id))');
	} else {
		$connection->query('CREATE TABLE bench_categories (id int AUTO_INCREMENT PRIMARY KEY, name varchar(100) NOT NULL)');
		$connection->query('CREATE TABLE bench_items (
			id int AUTO_INCREMENT PRIMARY KEY, name varchar(100) NOT NULL, quantity int NOT NULL, price double NOT NULL,
			is_active tinyint(1) NOT NULL, note text NULL, created_at datetime NOT NULL, updated_at datetime NULL,
			valid_from date NOT NULL, status varchar(20) NOT NULL, payload json NOT NULL,
			dimensions_width int NULL, dimensions_height int NULL,
			category_id int NOT NULL, CONSTRAINT bench_items_category FOREIGN KEY (category_id) REFERENCES bench_categories (id))');
		$connection->query('CREATE TABLE bench_comments (id int AUTO_INCREMENT PRIMARY KEY, text varchar(200) NOT NULL,
			item_id int NOT NULL, CONSTRAINT bench_comments_item FOREIGN KEY (item_id) REFERENCES bench_items (id))');
	}

	[$items, $categories, $comments] = generateRows($n);
	$connection->query('INSERT INTO bench_categories %values[]', $categories);
	foreach (array_chunk($items, 1000) as $chunk) {
		$connection->query('INSERT INTO bench_items %values[]', $chunk);
	}
	foreach (array_chunk($comments, 1000) as $chunk) {
		$connection->query('INSERT INTO bench_comments %values[]', $chunk);
	}
	// Dbal & ORM reflection is cached per process in MemoryStorage, nothing to invalidate.
}


/**
 * @return array{list<array<string, mixed>>, list<array<string, mixed>>, list<array<string, mixed>>}
 */
function generateRows(int $n): array
{
	$categories = [];
	for ($i = 1; $i <= 100; $i++) {
		$categories[] = ['id' => $i, 'name' => "Category $i"];
	}
	$items = [];
	$comments = [];
	$statuses = ['draft', 'active', 'archived'];
	$base = 1_700_000_000;
	for ($i = 1; $i <= $n; $i++) {
		$items[] = [
			'id' => $i,
			'name' => "Item number $i",
			'quantity' => $i % 1000,
			'price%f' => $i * 1.25,
			'is_active%b' => $i % 2 === 0,
			'note%?s' => $i % 3 === 0 ? null : "Some note for item $i",
			'created_at%s' => gmdate('Y-m-d H:i:s', $base + $i * 60),
			'updated_at%?s' => $i % 4 === 0 ? null : gmdate('Y-m-d H:i:s', $base + $i * 120),
			'valid_from%s' => gmdate('Y-m-d', $base + $i * 86400),
			'status' => $statuses[$i % 3],
			'payload%s' => json_encode(['tags' => ['a', 'b', (string) $i], 'score' => $i / 7, 'nested' => ['x' => $i]]),
			'dimensions_width%?i' => $i % 5 === 0 ? null : $i % 300,
			'dimensions_height%?i' => $i % 5 === 0 ? null : $i % 200,
			'category_id' => ($i % 100) + 1,
		];
		$comments[] = ['id' => $i, 'text' => "Comment for $i", 'item_id' => $i];
	}
	return [$items, $categories, $comments];
}


/**
 * Rows in entity format for the memory (array) mapper.
 * @return array{array<int, array<string, mixed>>, array<int, array<string, mixed>>, array<int, array<string, mixed>>}
 */
function generateArrayData(int $n): array
{
	[$items, $categories, $comments] = generateRows($n);
	$outItems = [];
	foreach ($items as $row) {
		$outItems[$row['id']] = [
			'id' => $row['id'],
			'name' => $row['name'],
			'quantity' => $row['quantity'],
			'price' => $row['price%f'],
			'isActive' => $row['is_active%b'],
			'note' => $row['note%?s'],
			'createdAt' => new DateTimeImmutable($row['created_at%s']),
			'updatedAt' => $row['updated_at%?s'] === null ? null : new DateTimeImmutable($row['updated_at%?s']),
			'validFrom' => new DateTimeImmutable($row['valid_from%s']),
			'status' => $row['status'],
			'payload' => json_decode($row['payload%s'], true),
			'dimensions' => ['width' => $row['dimensions_width%?i'], 'height' => $row['dimensions_height%?i']],
			'category' => $row['category_id'],
		];
	}
	$outCategories = [];
	foreach ($categories as $row) {
		$outCategories[$row['id']] = $row;
	}
	$outComments = [];
	foreach ($comments as $row) {
		$outComments[$row['id']] = ['id' => $row['id'], 'text' => $row['text'], 'item' => $row['item_id']];
	}
	return [$outItems, $outCategories, $outComments];
}


/**
 * Runs $fn $reps times (each with fresh state produced by $setup) and returns the minimum duration in ms.
 * @template T
 * @param callable(): T $setup
 * @param callable(T): mixed $fn
 */
function measure(string $name, int $reps, callable $setup, callable $fn): float
{
	global $phaseFilter;
	if ($phaseFilter !== null && preg_match($phaseFilter, $name) !== 1) return NAN;
	$best = INF;
	for ($i = 0; $i < $reps; $i++) {
		$state = $setup();
		gc_collect_cycles();
		$start = hrtime(true);
		$fn($state);
		$elapsed = (hrtime(true) - $start) / 1e6;
		$best = min($best, $elapsed);
		unset($state);
	}
	return $best;
}


/** @var array<int, array<string, float>> $results */
$results = [];

foreach ($sizes as $n) {
	$r = [];
	$newModel = fn () => createModel($cache, $connection, $useDi, $arrayData);

	if ($connection !== null) {
		$sql = 'SELECT * FROM bench_items ORDER BY id LIMIT %i';

		// --- 1. DBAL: query + fetch (driver + dbal normalization + Row + toArray)
		$r['dbal: query + driver fetch only (no Row)'] = measure('dbal: query + driver fetch only (no Row)', $reps, fn () => null, function () use ($connection, $sql, $n) {
			$adapter = $connection->query($sql, $n)->getAdapter();
			while ($adapter->fetch() !== null);
		});
		$r['dbal: query+fetch, no normalization'] = measure('dbal: query+fetch, no normalization', $reps, fn () => null, function () use ($connection, $sql, $n) {
			$result = $connection->query($sql, $n);
			$result->setValueNormalization(false);
			while (($row = $result->fetch()) !== null) $row->toArray();
		});
		$rows = [];
		$r['dbal: query+fetch (normalized) +toArray'] = measure('dbal: query+fetch (normalized) +toArray', $reps, fn () => null, function () use ($connection, $sql, $n, &$rows) {
			$rows = [];
			$result = $connection->query($sql, $n);
			while (($row = $result->fetch()) !== null) $rows[] = $row->toArray();
		});
		if ($rows === []) foreach ($connection->query($sql, $n) as $row) $rows[] = $row->toArray();

		// --- 2. conventions: storage row -> entity array
		$model = $newModel();
		$conventions = $model->getRepository(BenchItemsRepository::class)->getMapper()->getConventions(); // @phpstan-ignore-line
		$converted = [];
		$r['orm: Conventions::convertStorageToEntity'] = measure('orm: Conventions::convertStorageToEntity', $reps, fn () => null, function () use ($conventions, $rows, &$converted) {
			$converted = [];
			foreach ($rows as $row) $converted[] = $conventions->convertStorageToEntity($row);
		});
		if ($converted === []) foreach ($rows as $row) $converted[] = $conventions->convertStorageToEntity($row);
	} else {
		$model = $newModel();
		$converted = [];
		foreach ($arrayData[0] as $row) {
			$converted[] = $row;
			if (count($converted) === $n) break;
		}
	}

	// --- 3. IdentityMap::create split into its steps (replicated)
	$reflection = new ReflectionClass(BenchItem::class);
	$r['orm: newInstanceWithoutConstructor'] = measure('orm: newInstanceWithoutConstructor', $reps, fn () => null, function () use ($reflection, $n) {
		for ($i = 0; $i < $n; $i++) $reflection->newInstanceWithoutConstructor();
	});
	$r['orm: Repository::attach (onAttach+DI)'] = measure('orm: Repository::attach (onAttach+DI)', $reps, function () use ($newModel, $reflection, $n) {
		$entities = [];
		for ($i = 0; $i < $n; $i++) $entities[] = $reflection->newInstanceWithoutConstructor();
		return [$newModel()->getRepository(BenchItemsRepository::class), $entities];
	}, function ($state) {
		[$repository, $entities] = $state;
		foreach ($entities as $entity) $repository->attach($entity);
	});
	$r['orm: Entity::onLoad'] = measure('orm: Entity::onLoad', $reps, function () use ($newModel, $reflection, $n) {
		$repository = $newModel()->getRepository(BenchItemsRepository::class);
		$entities = [];
		for ($i = 0; $i < $n; $i++) {
			$entity = $reflection->newInstanceWithoutConstructor();
			$repository->attach($entity);
			$entities[] = $entity;
		}
		return $entities;
	}, function ($entities) use ($converted) {
		foreach ($entities as $i => $entity) $entity->onLoad($converted[$i]);
	});
	$r['orm: IdentityMap::create (total)'] = measure('orm: IdentityMap::create (total)', $reps, fn () => $newModel()->getRepository(BenchItemsRepository::class), function ($repository) use ($converted) {
		foreach ($converted as $row) $repository->hydrateEntity($row);
	});

	// --- 4. first & second access of each property (lazy property init: validation, wrappers, containers)
	$hydrate = function () use ($newModel, $converted) {
		$model = $newModel();
		$repository = $model->getRepository(BenchItemsRepository::class);
		$entities = [];
		foreach ($converted as $row) $entities[] = $repository->hydrateEntity($row);
		return [$model, $entities];
	};
	foreach (ITEM_PROPERTIES as $property) {
		if ($property === 'category' || $property === 'comments') continue;
		$r["prop 1st: $property"] = measure("prop 1st: $property", $reps, $hydrate, function ($state) use ($property) {
			foreach ($state[1] as $entity) $entity->getValue($property);
		});
	}
	$r['prop 2nd: all scalar/wrapped (sum)'] = measure('prop 2nd: all scalar/wrapped (sum)', $reps, function () use ($hydrate) {
		$state = $hydrate();
		foreach ($state[1] as $entity) foreach (ITEM_PROPERTIES as $p) if ($p !== 'category' && $p !== 'comments') $entity->getValue($p);
		return $state;
	}, function ($state) {
		foreach ($state[1] as $entity) foreach (ITEM_PROPERTIES as $p) if ($p !== 'category' && $p !== 'comments') $entity->getValue($p);
	});
	$r['rel: m:1 container init (getProperty)'] = measure('rel: m:1 container init (getProperty)', $reps, $hydrate, function ($state) {
		foreach ($state[1] as $entity) $entity->getProperty('category');
	});
	$r['rel: m:1 access (incl. preload query)'] = measure('rel: m:1 access (incl. preload query)', $reps, function () use ($hydrate) {
		$state = $hydrate();
		return [$state[0], $state[1], new \Nextras\Orm\Collection\EntityIterator($state[1])];
	}, function ($state) {
		foreach ($state[2] as $entity) $entity->category;
	});
	$r['rel: 1:m container init (getProperty)'] = measure('rel: 1:m container init (getProperty)', $reps, $hydrate, function ($state) {
		foreach ($state[1] as $entity) $entity->getProperty('comments');
	});

	// --- 5. end-to-end through the public API
	$r['e2e: findAll()->fetchAll()'] = measure('e2e: findAll()->fetchAll()', $reps, $newModel, function (Model $model) use ($n) {
		$model->getRepository(BenchItemsRepository::class)->findAll()->orderBy('id')->limitBy($n)->fetchAll();
	});
	$r['e2e: fetchAll + read all non-rel props'] = measure('e2e: fetchAll + read all non-rel props', $reps, $newModel, function (Model $model) use ($n) {
		foreach ($model->getRepository(BenchItemsRepository::class)->findAll()->orderBy('id')->limitBy($n) as $entity) {
			foreach (ITEM_PROPERTIES as $p) if ($p !== 'category' && $p !== 'comments') $entity->getValue($p);
		}
	});
	$r['e2e: fetchAll + all props + m:1'] = measure('e2e: fetchAll + all props + m:1', $reps, $newModel, function (Model $model) use ($n) {
		foreach ($model->getRepository(BenchItemsRepository::class)->findAll()->orderBy('id')->limitBy($n) as $entity) {
			foreach (ITEM_PROPERTIES as $p) if ($p !== 'comments') $entity->getValue($p);
		}
	});

	$results[$n] = $r;

	if ($profile && $n === max($sizes)) {
		$prof = new \ExcimerProfiler();
		$prof->setPeriod(0.0002);
		$prof->setEventType(EXCIMER_REAL);
		$model = $newModel();
		$prof->start();
		foreach ($model->getRepository(BenchItemsRepository::class)->findAll()->orderBy('id')->limitBy($n) as $entity) {
			foreach (ITEM_PROPERTIES as $p) if ($p !== 'comments') $entity->getValue($p);
		}
		$prof->stop();
		$agg = $prof->getLog()->aggregateByFunction();
		$total = array_sum(array_column($agg, 'self'));
		uasort($agg, fn ($a, $b) => $b['self'] <=> $a['self']);
		echo "\n== excimer: e2e fetch + all props (incl m:1), N=$n, top self time ==\n";
		printf("%-90s %7s %7s\n", 'function', 'self%', 'incl%');
		foreach (array_slice($agg, 0, 30, true) as $fn => $info) {
			printf("%-90s %6.1f%% %6.1f%%\n", substr($fn, 0, 90), 100 * $info['self'] / $total, 100 * $info['inclusive'] / $total);
		}
		file_put_contents(__DIR__ . "/../tmp/excimer-$driver.collapsed", $prof->getLog()->formatCollapsed());
	}
	unset($model, $converted, $rows);
}

if ($asJson) {
	// phases skipped by --phases are NAN, which JSON cannot encode
	$measured = array_map(fn (array $phases) => array_filter($phases, fn (float $ms) => !is_nan($ms)), $results);
	echo json_encode(['driver' => $driver, 'di' => $useDi, 'results' => $measured], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
	exit;
}

echo "\n== Hydration benchmark: driver=$driver di=" . ($useDi ? 'yes' : 'no') . " reps=$reps (min) PHP " . PHP_VERSION . " ==\n";
printf("%-44s", 'phase');
foreach ($sizes as $n) printf(" | %10s %8s", "N=$n ms", 'us/ent');
echo "\n" . str_repeat('-', 44 + 22 * count($sizes)) . "\n";
foreach (array_keys($results[$sizes[0]]) as $phase) {
	if (is_nan($results[$sizes[0]][$phase])) continue;
	printf("%-44s", $phase);
	foreach ($sizes as $n) {
		$ms = $results[$n][$phase];
		printf(" | %10.2f %8.3f", $ms, $ms * 1000 / $n);
	}
	echo "\n";
}
