## UniqueConstraintViolationException handling

A concurrent request can insert a row before your request does. Dbal converts a duplicate-key error into `UniqueConstraintViolationException`, which the application can catch. Use `Model::rollback()` to roll back pending writes and reset repository state:

```php
try {
    $like = new Like();
    $like->article = $article;
    $like->author = $author;
    $this->orm->persistAndFlush($like);
} catch (\Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException $e) {
    $this->orm->rollback();
    // Obtain fresh entities here, for example to find the already existing like.
}
```

Release references to loaded entities and entities involved in persistence/removal attempts after rollback. Their state has been invalidated. Discard the model if rollback or state cleanup fails. See [model rollback](model#rollback).
