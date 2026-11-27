## Async Fetching

`Nextras\Orm\Async\CollectionFetcher` fetches several collections — and their stored counts — at once. A typical use case is a grid or a filter page that needs the current page, the total count, and some option lists: instead of three queries waiting for each other, the database runs them at the same time.

```php
/** @var Nextras\Orm\Async\CollectionFetcher $fetcher (autowired) */
$filtered = $orm->books->findBy(['genre' => $genre]);

['items' => $items, 'total' => $total, 'authors' => $authors] = $fetcher->fetchAll([
	'items' => $filtered->orderBy('title')->limitBy(20, $offset),
	'total' => CollectionFetcher::count($filtered),
	'authors' => $orm->authors->findAll()->orderBy('name'),
]);

foreach ($items as $book) { /* no further query */ }
$total->get(); // int, the same as $filtered->countStored()
```

`fetchAll()` fetches the passed collections in place and returns the same array, so it can be destructured while keeping the collections' types. `CollectionFetcher::count()` creates a count request; read its value by `get()`.

Only the SQL is executed concurrently. Entities are always hydrated afterwards, one collection after another, in the calling code — the identity map is never accessed concurrently, and the gain is limited to the time the queries spend waiting for the database. Measure your workload: with a local database and large results, hydration dominates and the speedup is small; with network latency or heavier queries, it grows.

#### Query executors

How the queries run is decided by the `queryExecutor` option of the Nette extension (or the `CollectionFetcher` constructor):

- `Nextras\Orm\Async\SequentialQueryExecutor` (default) runs the queries one after another. It works everywhere; the code using the fetcher stays the same when you switch executors.
- `Nextras\Orm\Async\NativeAsyncQueryExecutor` runs the queries concurrently without any event loop, so it also works with **PHP-FPM**. Each query is sent without waiting for its result on its own connection — the main one plus extra connections opened on demand with the same configuration — and the results are collected afterwards. Supported for the `mysqli` driver (built with mysqlnd, the default) and the `pgsql` driver; for other drivers the queries run sequentially.
- `Nextras\Orm\Async\Swoole\SwooleQueryExecutor` runs each query in a Swoole coroutine on a pooled connection. Requires ext-swoole, enabled runtime hooks (`Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL)`, the default in Swoole servers), and a hookable driver: `mysqli`, `pdo_mysql`, or `pdo_pgsql` (the latter only when Swoole is built with `--enable-swoole-pgsql`). The native `pgsql` driver blocks the coroutine; queries are then correct, but not concurrent.

```neon
nextras.orm:
	queryExecutor: Nextras\Orm\Async\NativeAsyncQueryExecutor(maxConnections: 3)
	# or under Swoole:
	# queryExecutor: Nextras\Orm\Async\Swoole\SwooleQueryExecutor(poolSize: 8)
```

`NativeAsyncQueryExecutor` accepts `maxConnections` (including the main connection, default 4) and `extraConnectionConfig`, which overrides the config of the extra connections (e.g. `['host' => 'p:db.example.com']` for persistent mysqli connections).

**Mind the connection cost.** The extra connections live as long as the executor. In a long-running worker (FrankenPHP worker mode, RoadRunner, Swoole, a queue consumer) they are opened once and reused, and the fetcher pays off. Under classic PHP-FPM they are opened on every request: connecting takes several round trips plus DBAL's connection setup queries, which usually costs more than running a few fast queries concurrently saves — even mysqli persistent connections help only a little, as they are reset when reused. There, use it only for slow queries (heavy counts, aggregations).

Measured on a grid request (page of 50 filtered books with a join, its total count, 200 authors for a filter select), PHP 8.4, median of 25 runs, network latency simulated by `tc netem` (see `tests/benchmark/async-grid.php`):

| Added one-way latency | Driver | Sequential | Native, connections open | Native, connecting per request |
|---|---|---:|---:|---:|
| 0 ms | mysqli | 3.8 ms | 2.2 ms (1.71×) | 3.4 ms (1.10×) |
| 1 ms | mysqli | 6.6 ms | 3.5 ms (1.90×) | 18.6 ms (0.36×) |
| 5 ms | mysqli | 21.4 ms | 8.8 ms (2.43×) | 71.8 ms (0.30×) |
| 0 ms | pgsql | 3.5 ms | 2.3 ms (1.53×) | 17.7 ms (0.20×) |
| 1 ms | pgsql | 7.9 ms | 4.1 ms (1.96×) | 33.9 ms (0.23×) |
| 5 ms | pgsql | 22.0 ms | 8.6 ms (2.55×) | 103.8 ms (0.21×) |

#### Limitations

- Each concurrent query uses its own database connection; account for it in the database connection limit.
- Inside a transaction started through DBAL (`beginTransaction()`, `transactional()`, or Orm's own persisting), the queries run sequentially on the main connection, because the other connections could not see the uncommitted changes. A transaction started by a raw `START TRANSACTION` / `BEGIN` query is not detected — do not use the fetcher inside one. Session state set on the main connection at runtime (e.g., `SET` statements) is not applied to the extra connections either.
- The executors are meant for the plain SELECT queries of collections. Passing multi-statement queries, `COPY`, or procedures returning several result sets to them directly is not supported.
- Queries sent by the native and Swoole executors are not reported to the main connection's loggers (e.g., Tracy panel).
- Collections of relationships (e.g. `$author->books`, `->toCollection()`) are not supported and throw `NotSupportedException`. Collections not backed by DBAL (e.g. `ArrayCollection`) are passed through untouched, as are collections already fetched. A collection passed several times is fetched once.
- A collection changed or fetched between the query and the hydration (e.g. by an entity's `onLoad()` hook) keeps its own state and fetches again lazily.
- Use one `Model` per request. Under Swoole, a `Model` and its connection must not be shared by concurrently running requests.
