## Repository

Repository provides an interface for entities retrieving, persisting and removing.

<div class="advice">

In Orm, we use a coding standard which assumes that
- `get*` methods return an `IEntity` instance (or null, or throw an exception),
- `find*` methods return an `ICollection` instance.
</div>

#### Retrieving

Repository provides the `findAll()` method, which returns a `Nextras\Orm\Collection\ICollection` instance with all entities in storage. You can add filtering conditions, sort, and fetch entities from the collection. Read more about [Collection in its chapter](collection).

Repository must define a static method `getEntityClassNames()` that returns an array of entity names that the repository produces. Repository itself can contain user-defined methods:

```php
/**
 * @extends Repository<Book>
 */
final class BooksRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Book::class];
	}

	/**
	 * @return ICollection<Book>
	 */
	public function findLatest()
	{
		return $this->findAll()->orderBy('id', ICollection::DESC)->limitBy(3);
	}

	/**
	 * @return ICollection<Book>
	 */
	public function findByTags($name)
	{
		return $this->findBy(['tags->name' => $name]);
	}
}
```

Sometimes, it is necessary to write pure SQL queries. SQL queries can be written only in the mapper layer. You can easily tell the repository to proxy these methods by writing a phpdoc `@method` annotation:

```php
/**
 * @method ICollection<Book> findBooksWithEvenId()
 * @extends Repository<Book>
 */
final class BooksRepository extends Repository
{
	// ...
}

/**
 * @extends DbalMapper<Book>
 */
final class BooksMapper extends DbalMapper
{
	/** @return ICollection<Book> */
	public function findBooksWithEvenId(): ICollection
	{
		return $this->toCollection(
			$this->builder()->where('id % 2 = 0')
		);
	}
}
```

#### Identity map

Repository uses the Identity Map pattern. Therefore, only one instance of an entity can exist in your runtime. Selecting the same entity by another query will still return the same entity, even when entity changes were not persisted.

```php
// in this example title property is unique

$book1 = $orm->books->getById(1);
$book2 = $orm->books->findBy(['title' => $book1->title])->fetch();

$book1 === $book2; // true
```

#### Persisting

To save your changes, you must explicitly persist the changes by calling the `IModel::persist()` method, regardless of whether you are creating or updating the entity. By default, the repository will persist all other connected entities with persist cascade. Also, Orm will take care of the needed persistence ordering.

Persistence is run in a transaction. Calling `persist()` automatically starts a transaction if it was not started earlier. The transaction is committed by calling the `IModel::flush()` method. You can persist and flush changes at once by using the `IModel::persistAndFlush()` method. Persisting automatically attaches the entity to the repository if it has not been attached earlier.

```php
$author = new Author();
$author->name = 'Jon Snow';
$author->born = 'yesterday';
$author->mail = 'snow@wall.st';

$book = new Book();
$book->title = 'My Life on The Wall';
$book->author = $author;

// stores new book and author entity into database
// queries are run in transaction and committed
$orm->persistAndFlush($book);

```

You may disable cascade behavior in a `persist()` call by passing `false` as the second argument.

```php
$author = new Author();
$author->name = 'Jon Snow';
$author->born = 'yesterday';
$author->mail = 'snow@wall.st';

$book = new Book();
$book->title = 'My Life on The Wall';
$book->author = $author;

// will create only the author, not the book
$orm->persistAndFlush($author, false);
```

#### Removing

Use the `IRepository::remove()` method to delete entities from the database.

If an entity has a property with a `OneHasMany` relationship and the reverse relationship side is not nullable, removing this entity will cause an exception to be thrown. E.g., you cannot remove an author with books because the book entity has its author property as compulsory. The solution to this is:

1) Set a new author for the books:

	```php
	$author = $orm->authors->getById(...);
	$newAuthor = $orm->authors->getById(...);

	foreach ($author->books as $book) {
		$book->author = $newAuthor;
	}

	$orm->remove($author);
	```

2) Manually remove the books first:

	```php
	$author = $orm->authors->getById(...);
	foreach ($author->books as $book) {
		$orm->remove($book);
	}

	$orm->remove($author);
	```

3) Enable cascade removal; Cascade removal is not enabled by default; you can enable it by activating it in the relationship definition. See more in the [relationships chapter](Relationships).

	```php
	/**
	 * @property OneHasMany<Book> $books {1:m Book::$author, cascade=[persist, remove]}
	 */
	class Author extends Entity
	{}

	// this command will remove books first and then the author itself
	$orm->remove($author);
	```

	You may disable cascade behavior in a `remove()` call by passing `false` as the second argument.

	```php
	// will not use cascade if needed and will fail with an exception
	$orm->remove($author, false);
	```


Removing entities is run in a transaction as well as persisting. At the end, you must call the `IRepository::flush()` method or use the `IRepository::removeAndFlush()` method.
