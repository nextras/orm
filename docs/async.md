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
- `Nextras\Orm\Async\NativeAsyncQueryExecutor` runs the queries concurrently without any event loop, so it also works with **PHP-FPM**. Each query is sent without waiting for its result on its own connection — the main one plus extra connections opened on demand with the same configuration — and the results are collected afterwards. Supported for the `mysqli` and `pgsql` drivers.
- `Nextras\Orm\Async\Swoole\SwooleQueryExecutor` runs each query in a Swoole coroutine on a pooled connection. Requires ext-swoole, enabled runtime hooks (`Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL)`, the default in Swoole servers), and a hookable driver: `mysqli`, `pdo_mysql`, or `pdo_pgsql`. The native `pgsql` driver blocks the coroutine; queries are then correct, but not concurrent.

```neon
nextras.orm:
	queryExecutor: Nextras\Orm\Async\NativeAsyncQueryExecutor(maxConnections: 3)
	# or under Swoole:
	# queryExecutor: Nextras\Orm\Async\Swoole\SwooleQueryExecutor(poolSize: 8)
```

`NativeAsyncQueryExecutor` accepts `maxConnections` (including the main connection, default 4) and `extraConnectionConfig`, which overrides the config of the extra connections; for example, use `['host' => 'p:db.example.com']` to make the extra mysqli connections persistent and avoid opening them on each FPM request.

#### Limitations

- Each concurrent query uses its own database connection; account for it in the database connection limit.
- Inside a transaction, the queries run sequentially on the main connection, because the other connections could not see the uncommitted changes. Session state set on the main connection at runtime (e.g., `SET` statements) is not applied to the extra connections either.
- Queries sent by the native and Swoole executors are not reported to the main connection's loggers (e.g., Tracy panel).
- Collections of relationships (e.g. `$author->books`, `->toCollection()`) are not supported. Collections not backed by DBAL (e.g. `ArrayCollection`) are passed through untouched.
- Use one `Model` per request. Under Swoole, a `Model` and its connection must not be shared by concurrently running requests.
