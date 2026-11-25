## UniqueConstraintViolationException handling

A concurrent request can insert a row before your request does. Dbal converts a duplicate-key error into `UniqueConstraintViolationException`, which the application can catch.

Use `Model::transactional()` to roll back the failed operation and reset repository state. Create and load participating entities inside the callback, then catch the error outside it:

```php
try {
    $this->orm->transactional(function ($orm) use ($authorId, $articleId) {
        $like = new Like();
        $like->article = $orm->articles->getByIdChecked($articleId);
        $like->author = $orm->authors->getByIdChecked($authorId);
        $orm->persist($like);
    });
} catch (\Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException $e) {
    // Obtain fresh entities here, for example to find the already existing like.
}
```

After failure, release references to entities loaded or involved in persistence/removal attempts: their state has been invalidated. The callback is executed once; unique constraint violations are not retried automatically.

If you use `persistAndFlush()` directly, call `Model::rollback()` in the error handler to roll back the pending writes and reset repository state. Discard the model if rollback or state cleanup fails. See [model transactions](model#transactions-and-rollback).
