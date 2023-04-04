## Mapper

Mapper is the last ORM layer which communicates with the database. In contrast to the repository, mapper is storage specific, even more, also database specific. Everything database specific should be implemented only in the mapper layer. Orm comes with two predefined mappers: ArrayMapper, which works over a PHP array, and DbalMapper, which uses [Nextras Dbal][1] database abstraction layer.

#### ArrayMapper

Array mapper allows you to work with Orm without the database. `ArrayMapper` can be heavily used in tests. Mocking repositories and entities is not so easy, therefore you can use `TestMapper`, which will allow you to pass Orm's dependencies as in production mode, but test will not require any type of database connection. All Orm's integration tests are also run with Test mapper's storage.

Collection results form Array mapper are returned as an `ArrayCollection` instance.


#### DbalMapper

Dbal mapper uses [Nextras Dbal][1] library. Both Nextras Dbal and Orm support the following engines:
- MySQL,
- Postgres,
- SQL Server (currently not supported auto-update mapping).

To set mapper's database **table name** set `$tableName` property or override `getTableName()` method.

```php
/**
 * @extends DbalMapper<Book>
 */
class BooksMapper extends Nextras\Orm\Mapper\Dbal\DbalMapper
{
	protected $tableName = 'tbl_book';

	// or

	public function getTableName()
	{
		return 'tbl_book';
	}
}
```

If it is impossible to filter data by the repository layer API, you can write more detailed filtering in mapper. Dbal mapper allows querying by `Nextras\Dbal\QueryBuilder\QueryBuilder` or directly by SQL. The query builder instance will be injected into DbalCollection, raw SQL query will be executed and returned rows will be wrapped up in an ArrayCollection instance. DbalCollection is lazy, so it is always better to use Dbal's query builder.

You can get a new query builder instance by calling the `builder()` method. An instance of the current database connection is available in `$connection` property. Always wrap the result to collection with `toCollection()` call.

```php
/**
 * @extends DbalMapper<Book>
 */
class BooksMapper extends Nextras\Orm\Mapper\Dbal\DbalMapper
{
	/** @return Nextras\Orm\Collection\ICollection<Book> */
	public function getRandomBooksByBuilder(): Nextras\Orm\Collection\ICollection
	{
		return $this->toCollection(
			$this->builder()->addOrderBy('RAND()')
		);
	}

	/** @return Nextras\Orm\Collection\ICollection<Book> */
	public function getRandomBooksByQuery(): Nextras\Orm\Collection\ICollection
	{
		return $this->toCollection(
			$this->connection->query('SELECT * FROM tbl_books ORDER BY RAND()')
		);
	}
}
```

See [repository chapter](repository) to learn how to access methods from the mapper layer.


[1]: https://github.com/nextras/dbal
