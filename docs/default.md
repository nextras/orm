## Nextras Orm

Nextras Orm is the next generation Orm designed for efficiency and simple usage. It is highly customizable, you can use many extension points. Orm supports MySQL, Postgres or MS SQL Server.

#### Quick overview

First, define your entities. Entity definitions are quite short and pleasant to read.

```php
/**
 * @property int                    $id    {primary}
 * @property string                 $name
 * @property DateTimeImmutable|null $born  {default now}
 * @property string                 $email
 * @property OneHasMany|Book[]      $books {1:m Book::$author, orderBy=[id=DESC], cascade=[persist, remove]}
 */
class Author extends Entity {}

/**
 * @property int       $id        {primary}
 * @property string    $title
 * @property Author    $author    {m:1 Author::$books}
 * @property Publisher $publisher {m:1 Publisher::$books}
 */
class Book extends Entity {}

/**
 * @property int               $id    {primary}
 * @property string            $name
 * @property OneHasMany|Book[] $books {1:m Book::$publisher}
 */
class Publisher extends Entity {}
```

Let's create some new entities

```php
$author = new Author();
$author->name = 'Jon Snow';
$author->born = 'yesterday';
$author->mail = 'snow@wall.st';

$publisher = new Publisher();
$publisher->name = '7K publisher';

$book = new Book();
$book->title = 'My Life on The Wall';
$book->author = $author;
$book->publisher = $publisher;

$orm->books->persistAndFlush($book);
```

Calling `persistAndFlush()` on `$book` recursively persists the author, the publisher and the book entity and encloses the whole operation in a (database) transaction.

Nextras Orm provides a mechanism to use a constant number of queries: it does not matter, how much data you will fetch and ouput or how many inner cycles you will use. Orm will fetch all needed data efficiently in advance. Let's see an example:

```php
$authors = $orm->authors->findAll();
foreach ($authors as $author) {
	echo $author->name;

	foreach ($author->books as $book) {
		echo $book->title;
		echo $book->translator->name;

		foreach ($book->tags as $tag) {
			echo $tag->name;
		}
	}
}
```

The provided code will run **only 4 queries**:
1. select all authors,
2. select all books which were authored by previously selected authors,
3. select all translators who translated the previously selected books,
4. select all tags for previously selected books.

As you can see, Orm will not query in each cycle pass, rather it will query all the data at once during the first pass.
