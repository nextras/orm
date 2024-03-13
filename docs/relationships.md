## Relationships

Orm provides a very efficient way to work with entity relationships. Orm recognizes 4 types of relationship:

- **1:m** - one has many: *author has many books*
- **m:1** - many has one: *book has one author*
- **m:m** - many has many: *book has many tags, tag is associated with many books*
- **1:1** - one has one: *user has one setting*, the reference for a related entity is stored only on the side that is marked as main.

Use a relationship modifier to define relationship property. Modifiers require you to define a target entity, some modifiers need to be defined on both sides, then the reverse property definition is compulsory. If you want to define only one-sided relationship, use `oneSided=true` parameter. Other parameters are optional: ordering, setting a cascade, or making the current side primary (persisting is driven by the primary side). At least one side of `m:m` or `1:1` has to be defined as the primary. Relationships do not support getters and setters as other entity properties.

```
{1:1 EntityName::$reversePropertyName}
{m:1 EntityName::$reversePropertyName}
{1:m EntityName::$reversePropertyName, orderBy=property}
{m:m EntityName::$reversePropertyName, isMain=true, orderBy=[property=DESC, anotherProperty=ASC]}
```

`1:m` and `m:m` relationships can define collection's default ordering by `orderBy` property. You may provide either a property name, or an associated array where the key is a property expression, and the value is an ordering direction.

The property mapping into an actual column name may differ depending on the [conventions](conventions). By default, Orm strips "id" suffixes when the column contains a foreign key reference.

Cascade
-------

All relationships can have defined a cascade behavior. Cascade behavior defines if entity persistence or removal should affect other connected entities. By default, all relationships have a cascade for `persist`. To define cascade use an array of keywords: `persist` and `remove`. Cascade works for every type of relationship.

```
// persist cascade is the default
{relModifier EntityName::$reversePropertyName} // equals to
{relModifier EntityName::$reversePropertyName, cascade=[persist]}

// adding remove cascade; you have to redefine persist cascade
{relModifier EntityName::$reversePropertyName, cascade=[persist, remove]}

// to disable persist cascade, provide empty cascade definition
{relModifier EntityName::$reversePropertyName, cascade=[]}
```

The `persist()` and `remove()` methods process entity with its cascade. You can turn off by the second optional method argument:

```php
$usersRepository->persist($user, false);
$usersRepository->remove($user, false);
```

#### 1:M / M:1 -- Bidirectional

```php
use Nextras\Orm\Relationships\OneHasMany;

/**
 * @property int               $id               {primary}
 * @property OneHasMany<Book>  $books            {1:m Book::$author}
 * @property OneHasMany<Book>  $translatedBooks  {1:m Book::$translator}
 */
class Author extends Nextras\Orm\Entity\Entity
{}

/**
 * @property int     $id          {primary}
 * @property Author  $author      {m:1 Author::$books}
 * @property Author  $translator  {m:1 Author::$translatedBooks}
 */
class Book extends Nextras\Orm\Entity\Entity
{}
```


#### M:1 -- One-sided

```php
/**
 * @property int     $id          {primary}
 * @property Author  $author      {m:1 Author, oneSided=true}
 * @property Author  $translator  {m:1 Author, oneSided=true}
 */
class Book extends Nextras\Orm\Entity\Entity
{}
```


#### 1:M / M:1 -- Self-referencing

```php
use Nextras\Orm\Relationships\OneHasMany;

/**
 * @property int                   $id          {primary}
 * @property Category              $parent      {m:1 Category::$categories}
 * @property OneHasMany<Category>  $categories  {1:m Category::$parent}
 */
class Category extends Nextras\Orm\Entity\Entity
{}
```

#### M:M -- Bidirectional

```php
use Nextras\Orm\Relationships\ManyHasMany;

/**
 * @property int               $id    {primary}
 * @property ManyHasMany<Tag>  $tags  {m:m Tag::$books, isMain=true}
 */
class Book extends Nextras\Orm\Entity\Entity
{}

/**
 * @property int                $id     {primary}
 * @property ManyHasMany<Book>  $books  {m:m Book::$tags}
 */
class Tag extends Nextras\Orm\Entity\Entity
{}
```

#### M:M -- One-sided

Only the non-main side is optional.

```php
use Nextras\Orm\Relationships\ManyHasMany;

/**
 * @property int               $id    {primary}
 * @property ManyHasMany<Tag>  $tags  {m:m Tag, isMain=true, oneSided=true}
 */
class Book extends Nextras\Orm\Entity\Entity
{}
```


#### M:M -- Self-referencing

```php
use Nextras\Orm\Relationships\ManyHasMany;

/**
 * @property int                $id             {primary}
 * @property ManyHasMany<User>  $myFriends      {m:m User::$friendsWithMe}
 * @property ManyHasMany<User>  $friendsWithMe  {m:m User::$myFriends}
 */
class User extends Nextras\Orm\Entity\Entity
{}
```


#### 1:1 -- Bidirectional

Reference will be stored in the `book.ean_id`.

```php
/**
 * @property int  $id   {primary}
 * @property Ean  $ean  {1:1 Ean::$book, isMain=true}
 */
class Book extends Nextras\Orm\Entity\Entity
{}

/**
 * @property int   $id    {primary}
 * @property Book  $book  {1:1 Book::$ean}
 */
class Ean extends Nextras\Orm\Entity\Entity
{}
```

#### 1:1 -- One-sided

Only the not-main side is optional. Reference will be stored in the `book.ean_id`.

```php
/**
 * @property int  $id   {primary}
 * @property Ean  $ean  {1:1 Ean, isMain=true, oneSided=true}
 */
class Book extends Nextras\Orm\Entity\Entity
{}
```



#### 1:1 -- Self-referencing

Reference will be stored in `book.next_volume_id`.

```php
/**
 * @property int        $id              {primary}
 * @property Book|null  $nextVolume      {1:1 Book::$previousVolume, isMain=true}
 * @property Book|null  $previousVolume  {1:1 Book::$nextVolume}
 */
class Book extends Nextras\Orm\Entity\Entity
{}
```

------------

### Relationship interfaces

The example above introduces classes which weren't mentioned before: `OneHasMany` and `ManyHasMany`. Instances of these classes are injected into the property and provide some cool features. The main responsibility is the implementation of `\Traversable` interface. You can iterate over the property to get the entities in the relationship.

```php
foreach ($author->books as $book) {
	$book instanceof Book; // true
}
```

Also, you can use convenient methods to add, remove, and set entities in the relationship. The relationship automatically updates the reverse side of the relationship (if loaded).

```php
$author->books->add($book);
$author->books->remove($book);
$author->books->set([$book]);

$book->tags->add($tag);
$book->tags->remove($tag);
$book->tags->set([$tag]);
```

The relationship property wrapper accepts both entity instances and an id (primary key value). If you pass an id, Orm will load the proper entity automatically. This behavior is available only if the entity is "attached" to the repository (fetched from storage, directly attached or indirectly attached by another attached entity).

```php
$book->author = 1;
$book->author->id === 1; // true

$book->tags->remove(1);
```

Because the relationship may not be nullable on neither of the sides, replacing its value means that the original instance has to be either reattached elsewhere or removed. To shortly allow an invalid state (e.g., a book with no ean or an ean wit no book), you have to retrieve the `HasOne` relationship property and use its `set()` method with optional argument `$allowNull`. In the following example, Book and Ean are in OneHasOne relationship, therefore updating the Ean is a bit more complicated.

```php
$originalEan = $book->ean;

$eanProperty = $book->getProperty('ean');
$eanProperty->set(new Ean(), allowNull: true);

// now the original ean has $book a null, reading such property will throw;
// either set a new Book to it or remove the original ean;

$eanRepository->remove($originalEan);
```

#### Collection interface

Sometimes, it is useful to work with the relationship as with collection to make further adjustments. Simply call `toCollection()` to receive a collection over the relationship.

```php
$collection = $author->books->toCollection();
$collection = $collection->limitBy(3);
```

Working with such a collection will preserve optimized loading for other entities in the original collection.

```php
$authors = $orm->authors->findById([1, 2]); // fetches 2 authors

foreach ($authors as $author) {
	// 1st call for author #1 fetches data for both authors by
	// (SELECT ... WHERE author_id = 1 LIMIT 2) UNION ALL (SELECT ... WHERE author_id = 2 LIMIT 2)
	// and returns data just for the author #1.
	//
	// 2nd call for author #2 uses already fetched data.
	$sub = $author->books->toCollection()->limitBy(2);
}
```

You may decide to expose only the relationship's collection in its property. Use `exposeCollection` modifier argument. Further modifications are still allowed through the relationship object returned by `IEntity::getProperty()` method.

```php
/**
 * @property int|null           $id
 * @property ICollection<Book>  $books  {1:m Author::$books, exposeCollection=true}
 */
class Author
{
    public function setBooks(Book ...$books): void
    {
        $this->getProperty('books')->set($books);
    }
}

$author = new Author();
$author->books->findBy(...);
$author->setBooks(new Book());
```
