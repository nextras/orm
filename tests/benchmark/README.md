# Hydration benchmark

Imported from `mrceperka/orm:perf/read-path`, commit `b104c2e8` (benchmark files only).
Discarded return values are assigned to a temporary variable to avoid PHP 8.5
unused-return-value warnings while remaining compatible with PHP 8.1.

Run from the repository root after installing Composer dependencies:

```sh
XDEBUG_MODE=off php -d memory_limit=1G tests/benchmark/hydration.php --driver=pgsql --n=1000,10000 --reps=5
```

Use `--driver=pgsql` or `--driver=mysql` to include DBAL fetching and storage
conversion, using the corresponding section of `tests/databases.ini`. The script
creates and seeds `bench_categories`, `bench_items`, and `bench_comments`; it drops
and recreates these tables when there are too few items. Use a test database.
For Docker, prefix the PHP command with `docker compose exec -T -e XDEBUG_MODE=off php`.

Each measurement reports the minimum duration across repetitions, with setup
outside the timed region. Keep the PHP version, database, options, and environment
the same when comparing changes. The array driver excludes database work.

Save results before and after a change:

```sh
XDEBUG_MODE=off php -d memory_limit=1G tests/benchmark/hydration.php --driver=pgsql --n=1000,10000 --reps=5 --di --json > /tmp/hydration-before.json
# Apply the change, then run the same command with /tmp/hydration-after.json.
```

Options:

- `--di`: attach the Nette DI dependency provider.
- `--phases='m:1|e2e'`: measure only phases matching the regular expression.
- `--nogc`: disable automatic cycle collection (explicit collection still runs before measurements).
- `--profile`: profile an additional end-to-end run with the optional Excimer extension;
  writes `tests/tmp/excimer-<driver>.collapsed`. Run separately from `--json`.

## Relationship scaling

`relationships.preload.scaling.phpt` is copied from the same branch's integration
test, with the larger dataset increased from 8,000 to 50,000 books. It remains
optional, outside `tests/cases`. It prints measurements before checking scaling.
The threshold is adjusted from 14 to 87.5 to retain the original 1.75-times margin
over linear growth when increasing the dataset fiftyfold.

Run against the configured test databases (locally PostgreSQL):

```sh
XDEBUG_MODE=off php -d memory_limit=1G vendor/bin/tester -C -d memory_limit=1G -j 1 -o console-lines tests/benchmark/relationships.preload.scaling.phpt
```

This uses the integration-test schema and resets its fixture data before each
measurement method. The schema must already be initialized by the test setup.
SQL snapshots are disabled for this benchmark using the imported `snapshotQueries`
switch in `tests/inc/TestCase.php`.

The timing checks take the minimum of three runs at 1,000 and 50,000 books. The
m:1 case traverses book authors; the 1:m cases traverse authors' books, with two
books per author (500 versus 25,000 authors). Memory is measured as retained PHP
memory growth after traversal, not peak memory. The linear-scaling assertions can
fail on code that still has the performance issue.

Tester hides measurements for passing checks. To see the memory figures directly:

```sh
XDEBUG_MODE=off php -d memory_limit=1G tests/benchmark/relationships.preload.scaling.phpt '--dataprovider=pgsql|tests/databases.ini' --method=testOneHasManyTraversalMemoryScalesLinearly
```

### Local comparison: cached preload-values hash

Measured on 2026-10-15 with PHP 8.5.11, PostgreSQL, Xdebug disabled, and a 1 GiB
PHP memory limit. Timing is the minimum of three repetitions at 50,000 books;
memory measurements use a fresh PHP process. The previous batch already used
xxh3 and `serialize()` but recomputed the values encoding on every access.

| Measurement | Previous batch | Cached serialize hash | Cached JSON hash |
|---|---:|---:|---:|
| m:1 traversal | 42.196 s | 1.151 s (36.7x faster) | 1.167 s |
| 1:m traversal | 11.262 s | 1.051 s (10.7x faster) | 1.077 s |
| 1:m retained memory | 292.64 MiB | 293.02 MiB | 293.02 MiB |

For the cached serialize hash, the 1,000-book timings were 21.64 ms (m:1) and 20.59 ms
(1:m), giving growth ratios of 53.18 and 51.04 for 50 times as many books.
All three scaling assertions pass. This benchmark does not time m:n traversal
or alternating filtered queries; those paths have separate correctness tests.

The JSON alternative uses `json_encode(..., JSON_THROW_ON_ERROR)` for preload
values, including the fallback without a preload container. Query parameters
still use `serialize()`. Its 1,000-book timings were 21.57 ms (m:1) and 24.99 ms
(1:m), giving growth ratios of 54.12 and 43.11; all scaling assertions pass.
This run shows no end-to-end improvement over cached serialize. The small timing
differences may be run-to-run noise: encoding now happens only once per batch.
