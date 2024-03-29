## Model

Model is the central Orm manager, it provides repositories and manages their loading. Model requires a repository loader. Model itself ensures the persistence, removal and refresh behavior synchronization across repositories.

#### Persist and Remove

Call `persist()` or `remove()` method to process the passed entity. Persist internally handles the needed insert or update database call. At the end of your work, you should confirm your changes by flushing them. Flushing is internally implemented as a transaction commit; transactions are automatically opened with the first persist or remove call. Not only newly created entities, but also **already persisted entities must be persisted once more to promote its changes to the storage**. This behavior differs from Doctrine, which automatically saves all changes in attached entities.

```php
$user = $model->users->getById(1);
$user->isEmailSubscribed = false;

$model->persist($user);
$model->flush();
// or
$model->persistAndFlush($user);
```

To remove call `remove()` method.

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


#### Refresh

In some use-cases it is needed to refresh the entity data from the storage, where they may have been changed. However, just repeated fetching entity from repository does not return updated entity though it may run new database select query. This is due to Identity map and Orm data consistency. To solve the refresh need Orm provides `Model::refreshAll()` method which will refresh all entities from the storage.

```php
$book = $model->books->getById(1);

sleep(60); // sleep for one minute

$model->refreshAll();
// $book is updated with the latest data from database
```

Also, some entities may be changed but not persisted. Calling `refreshAll()` in such case will throw an `Nextras\Orm\Exception\InvalidStateException` exception. You may allow data override by passing `true` to the method.

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

Batch processing is often memory demanding. To free some memory, you may use `IModel::clear()` method. Calling clear will clear all caches and references to all fetched entities, it also nulls their values & data. Be aware that you should never access these entity after calling the clear method. Also, be careful not to store any references to these entities.

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
