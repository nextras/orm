## UniqueConstraintViolationException handling

Quite often you may encounter a race condition when an entry already exists in the database and inserting another one causes unique constraint failure. Dbal converts this database error into a `UniqueConstraintViolationException`, so you may catch it and mitigate it.

1) First, try/catch the exception, to prevent your app from crashing.
2) Rollback the invalid query on the DB connection. Orm itself on the repository layer does not know about storage implementation therefore it's your responsibility to clean up the consequences.
3) Refresh the model to retrieve the current db state.

In the example below, the persist action may fail because there is already a like for a specific author & article.

```php
try {
	$like = new Like();
	$like->article = $article;
	$like->author = $author;
	$this->orm->likes->persistAndFlush($like);
} catch (\Nextras\Dbal\Drivers\Exception\UniqueConstraintViolationException $e) {
	$this->orm->likes->getMapper()->rollback();
	$this->orm->refreshAll()
}
```
