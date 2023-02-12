## Collection

Collection of entities is returned as an instance implementing `Nextras\Orm\Collection\ICollection` interface. `ICollection` extends `\Traversable` interface and adds another API to do further operations with the collection.

<div class="advice">

In Orm, we use coding standard which assumes that
- `get*` methods return an `IEntity` instance or (a null or throws),
- `find*` methods return an `ICollection` instance.
</div>

Collection itself is **immutable**, all methods that modify the collection return a new `ICollection` instance. Collection provides following methods:

| Function                                                         | Description                                                                                                                    |
|------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|
| `getBy(array $conds): ?IEntity`                                  | applies an additional filtering and returns the first result's entity or a `null`                                              |
| `getByChecked(array $conds): IEntity`                            | applies an additional filtering and returns the first result's entity or a throws `NoResultException`                          |
| `getById($primaryValue): ?IEntity`                               | applies filtering by `id` property and returns the first result's entity or a `null`                                           |
| `getByIdChecked($primaryValue): IEntity`                         | applies filtering by `id` property and returns the first result's entity or a throws `NoResultException`                       |
| `findBy(array $conds): ICollection<IEntity>`                     | applies an additional filtering                                                                                                |
| `orderBy($property, $direction): ICollection<IEntity>`           | applies an additional ordering                                                                                                 |
| `orderBy($propertyExpression, $direction): ICollection<IEntity>` | applies an additional ordering using collection function                                                                       |
| `orderBy(array $properties): ICollection<IEntity>`               | applies an additional multiple ordering                                                                                        |
| `resetOrderBy(): ICollection<IEntity>`                           | removes all defined orderings                                                                                                  |
| `limitBy($limit, $offset): ICollection<IEntity>`                 | limits the collection and sets the starting offset                                                                             |
| `fetch(): ?IEntity`                                              | returns the next unprocessed result's entity, repeated calls iterate over the whole result-set                                 |
| `fetchChecked(): IEntity`                                        | returns the next unprocessed result's entity, repeated calls iterate over the whole result-set or a throws `NoResultException` |
| `fetchAll(): IEntity[]`                                          | returns the all result's entities as an array                                                                                  |
| `fetchPairs($key, $value): array`                                | process the whole result and returns it as an associative array                                                                |

#### Single result fetching

The same condition format may be applied to retrieve just the first collection's result.

```php
$author = $orm->author->getBy(['name' => 'Peter', 'age' => 23]); // Author|null
if ($author !== null) {
	echo $author->name;
}

$author = $orm->author->getByChecked(['name' => 'Peter', 'age' => 23]); // returns Author or throws NoResultException
echo $author->name;
```

The most common use-case to retrieve an entity by its primary value has a shortcut `getById()` and `getByIdChecked()`.

```php
$author = $orm->author->getById(1); // Author|null
// equals
$author = $orm->author->getBy(['id' => 1]);

$author = $orm->author->getByIdChecked(2); // returns Author or throws NoResultException
// equals
$author = $orm->author->getByChecked(['id' => 2]);
```

#### Filtering

Read more in the [collection filtering chapter](collection-filtering).


#### Sorting

You can easily sort the collection by an `orderBy()` method; The `orderBy()` method accepts a property name and a sorting direction. By default, values are sorted in ascending order.

To change the order, use `ICollection::ASC` or `ICollection::DESC` constants. If the sorting property (or property expression) may contain a null value, use more specific sorting constants: `ICollection::ASC_NULLS_LAST`, `ICollection::ASC_NULLS_FIRST`, `ICollection::DESC_NULLS_LAST`, or `ICollection::DESC_NULLS_FIRST`.

```php
$orm->books->findAll()->orderBy('title'); // ORDER BY title ASC
$orm->books->findAll()->orderBy('title', ICollection::DESC); // ORDER BY title DESC
```

The `orderBy` method also accepts a property expression. See [aggregation in collection filtering chapter](collection-filtering#toc-aggregation).

```php
// ORDER BY age = 2
$orm->books->findAll()->orderBy([
    CompareEqualsFunction::class,
    'age',
    '2',
]);
```

You can add more ordering rules; they will be used if the previously defined ordering properties will be evaluated as equal. To add more ordering rules, call `orderBy` method repeatedly or simply use `orderBy` method with an array of property names and their sorting directions. All already defined ordering rules may be removed by `resetOrderBy()` method.

```php
// ORDER BY title DESC, publishedYear DESC
$orm->books->findAll()->orderBy([
    'title' => ICollection::ASC,
    'publishedYear' => ICollection::DESC,
]);
```

#### Limiting

To limit the data collection, just use `limitBy()` method. The first argument is a limit, the second optional argument is a starting offset.

```php
// get the last 10 published books
$orm->books->findAll()->orderBy('publishedAt', ICollection::DESC)->limitBy(10);

// get the 10 penultimate published books
$orm->books->findAll()->orderBy('publishedAt', ICollection::DESC)->limitBy(10, 10);
```

#### Counting

It is easy to count entities returned in a collection. There are two methods:
- `count()` fetches the queried entities from the storage and counts them in PHP,
- `countStored()` asks the storage for the matching entities' count; the implementation depends on the mapper layer, basically, the `countStored()` method runs an COUNT SQL query.

The `count()` method is quite useful if you know that you will need the fetched entities later. The `countStored()` is needed if you do a pagination, etc.

```php
public function renderArticles(int $categoryId): void
{
	$articles = $this->orm->articles->findBy(['category' => $categoryId]);

	$limit = 10;
	$offset = $this->page * 10;

	$this->paginator->totalCount = $articles->countStored();
	$this->template->articles = $articles->limitBy($limit, $offset);
}
```
```html
{if $articles->count()}
	{foreach $articles} ... {/foreach}
{else}
	You have no articles.
{/if}
```

#### Pairs fetching

The `fetchPairs()` method accepts two arguments: the first argument is a property name that will be used as an array key. If a null is provided, the result array will be as a list (i.e. from zero). The second argument is a property name that the value will be read from. If a `null` is provided, then the whole entity will be used as the value.

```php
// all book entities indexed by their primary key
$orm->books
	->findAll()
	->fetchPairs('id', null);

// all books' titles sorted backward and naturally indexed
$orm->books
	->findAll()
	->orderBy('title', ICollection::DESC)
	->fetchPairs(null, 'title');
```
