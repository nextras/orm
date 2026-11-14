## Model

Model is the central Orm manager; it provides repositories and manages their loading. Model requires a repository loader. Model itself ensures the persistence, removal, and refresh behavior synchronization across repositories.

#### Persist and Remove

Call the `persist()` or `remove()` method to process the passed entity. Persist internally handles the needed insert or update database call. At the end of your work, you should confirm your changes by flushing them. Flushing is internally implemented as a transaction commit; transactions are automatically opened with the first persist or remove call. Not only newly created entities, but also **already persisted entities must be persisted once more to promote their changes to the storage**. This behavior differs from Doctrine, which automatically saves all changes in attached entities.

```php
$user = $model->users->getById(1);
$user->isEmailSubscribed = false;

$model->persist($user);
$model->flush();
// or
$model->persistAndFlush($user);
```

To remove, call the `remove()` method.

```php
$user = $model->users->getById(1);
$model->remove($user);
$model->flush();
// or
$model->removeAndFlush($user);
```

You may totally disable cascade behavior by passing the second optional `false` argument. However, this may lead to inconsistencies between what is stored in the database and what is not, then it's your responsibility to persist properly.

```php
$author = new Author();
$book = new Book();
$book->author = $author;

// with auto-cascade
$model->persit($book);
$model->flush();

// or without auto-cascade
$model->persist($author, false);
$model->persist($book, false);
$model->flush();
```


#### Transactions and rollback

`Model::transactional()` runs a callback once in a transaction, flushes its writes, and commits before returning the callback result. The transaction starts before the callback, including its reads. Call `persist()` explicitly for each changed entity as usual.

```php
$userId = 123;
$result = $model->transactional(function ($model) use ($userId) {
    $user = $model->users->getByIdChecked($userId);
    $user->isEmailSubscribed = false;
    $model->persist($user);
    return $user->id;
});
```

This API initializes every repository to verify that all mappers support transaction callbacks and share one transaction coordinator. Dbal mappers on the same connection must use the same `DbalMapperCoordinator` instance. Nested model transaction callbacks, an existing connection transaction, and unflushed writes from before the callback are rejected before the callback runs. Array/file mappers support rollback but do not support transaction callbacks.

Calling `flush()` or `persistAndFlush()` inside the callback does not commit the transaction. The outer scope remains responsible for commit. Existing persistence and flush events still run before the final commit; do not use them as confirmation of a successful commit. Load entities inside the callback when their reads must belong to the transaction; already cached entities are not automatically reloaded.

If the callback, persistence, flush, or commit throws, the transaction is rolled back and repository state is reset. Loaded entities and entities involved in persistence/removal attempts are invalidated: release those references and obtain fresh entities for subsequent work. A persistence or flush failure also aborts the scope when the callback catches that failure. No automatic retries are performed. Catch errors outside `transactional()`.

For work performed with the existing `persist()` / `flush()` API, `Model::rollback()` rolls back the initialized mappers and resets their repository state:

```php
try {
    $model->persist($entity);
    $model->flush();
} catch (\Throwable $e) {
    $model->rollback();
    // Release $entity and all other affected entity references.
    throw $e;
}
```

Rollback resets identity maps, refresh markers, relationship caches, and pending flush notifications, including failed writes and removed entities. It does not restore old PHP objects to their earlier values. Calling `rollback()` inside a transaction callback is rejected; throw an exception to abort the scope instead.

Rollback and reset attempt cleanup for every initialized repository. If cleanup fails, `Nextras\Orm\Exception\RollbackException` exposes the cleanup failures through `getCleanupErrors()` and retains the original transaction failure as its previous exception when available. Discard the model and affected entities after cleanup fails. A Dbal transaction rollback failure retains DBAL's `TransactionRollbackException`, and its coordinator cannot be reused.


#### Refresh

In some use cases, it is necessary to refresh the entity data from the storage, where they may have been changed. However, just repeatedly fetching an entity from the repository does not return an updated entity though it may run a new database select query. This is due to the Identity map and Orm data consistency. To solve the refresh need, Orm provides the `Model::refreshAll()` method which will refresh all entities from the storage.

```php
$book = $model->books->getById(1);

sleep(60); // sleep for one minute

$model->refreshAll();
// $book is updated with the latest data from database
```

Also, some entities may be changed but not persisted. Calling `refreshAll()` in such a case will throw a `Nextras\Orm\Exception\InvalidStateException` exception. You may allow data override by passing `true` to the method.

```php
$book = $model->books->getById(1);
$book->title = 'Test';

$model->persistAndFlush($book);

$book->title = 'Changed title';

$model->refreshAll(true);
assert($book->title === 'Test');
// the title may be actually different, because another process may have changed it
// the "Changed title" value is discarded and replaced by the actual database value
```


#### Clear

Batch processing is often memory demanding. To free some memory, you may use the `IModel::clear()` method. Calling clear will clear all caches and references to all fetched entities; it also nulls their values and data. Be aware that you should never access these entities after calling the clear method. Also, be careful not to store any references to these entities.

```php
$lastId = 0;
do {

	$fetched = $model->books->findBy(['id>' => $lastId])->orderBy('id')->limitBy(1000)->fetchAll();
	foreach ($fetched as $book) {
		// do the work

		$lastId = $book->id;
	}

	// release the entities from the memory
	$model->clear();

} while (!empty($fetched));

// optionally, the final memory release
unset($fetched, $book);
$model->clear();
```
