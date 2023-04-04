## Conventions

The database naming conventions shouldn't affect your PHP naming conventions. Orm is designed to help you not to bother with your database naming relics.


#### Table name

Table names are directly resolved in the mapper layer; they are derived from the mapper class name. By default, the names are created as an underscored name of the mapper class with stripped "Mapper" suffix, e.g. `EventsMapper` -> `events`.

If you would like to force some other table name, define `$tableName` property, or override `getTableName()` method in the mapper class.

```php
use Nextras\Orm\Mapper\Dbal\DbalMapper;

/**
 * @extends DbalMapper<Event>
 */
class EventsMapper extends DbalMapper
{
	protected $tableName = 'events';

	// or

	protected function getTableName(): string
	{
		return 'blog_events';
	}
}
```


#### Properties

Conventions take care about converting column names to property names. Dbal mapper's conventions are represented by interface `Nextras\Orm\Mapper\Dbal\Conventions\IConventions` interface.

Orm comes with two predefined inflectors, that modify the basic conventions' behavior:
- CamelCaseInflector
- SnakeCaseInflector

These predefined classes assume "camelCase" naming in the entity layer and transform it for the database layer. (CamelCase reflector actually does not do any transformation.)

- If database column has `_id` (or `Id`) suffix and is defined as a foreign key, the inflector automatically strips the suffix.
- If database table has only one primary column, it is automatically mapped to the primary property in an entity (`$id`).

You are free to add your own mapping. Just call `setMapping($entityName, $storageName)` method. The right way to do this is to inherit `createConventions()` method in your mapper class.

```php
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;

/**
 * @extends DbalMapper<Event>
 */
class EventsMapper extends DbalMapper
{
	protected function createConventions(): IConventions
	{
		$conventions = parent::createConventions();
		$conventions->setMapping('entityProperty', 'database_property');
		return $conventions;
	}
}
```


#### Properties' converters

Conventions offer an API for data transformation when the data are passed from storage to PHP and otherwise. The aforementioned `setMapping($entityName, $storageName, $toEntityCb, $toStorageCb)` method has two optional parameters that accept callbacks. These callbacks receive the value and key parameters and must return the new converted value. The first callback is for conversion from the storage to PHP, the second is for conversion from PHP to the storage. Let's see an example:

```php
/**
 * @param bool $isPublic
 */
class File extends Nextras\Orm\Entity\Entity
{
}

/**
 * @extends DbalMapper<File>
 */
class FilesMapper extends Nextras\Orm\Mapper\Dbal\DbalMapper
{
    protected function createConventions(): Nextras\Orm\Mapper\Dbal\Conventions\IConventions
    {
        $conventions = parent::createConventions();
        $conventions->setMapping('isPublic', 'is_public', function ($val) {
            return $val === 'Y' || $val === 'y';
        }, function ($val) {
            return $val ? 'Y' : 'N';
        });
        return $conventions;
    }
}
```


#### Properties' modifiers for Nextras Dbal

The underlying layer Nextras Dbal takes care about converting and sanitizing the values for SQL INSERT/UPDATE query. By default, the `%any` modifier is used and the value is transformed by its type. However, you may want to force different behaviour and modifiers for Nextras Dbal layer. To do that, use `setModifier($storageKey, $modifier)` method, which accepts the table's column name and Dbal's modifier. Let's see an example:

```php
/**
 * @param string $contents
 */
class File extends Nextras\Orm\Entity\Entity
{
}

/**
 * @extends DbalMapper<File>
 */
class FilesMapper extends Nextras\Orm\Mapper\Dbal\DbalMapper
{
    protected function createConventions(): Nextras\Orm\Mapper\Dbal\Conventions\IConventions
    {
        $conventions = parent::createConventions();
        $conventions->setModifier('contents', '%blob');
        return $conventions;
    }
}
```


#### HasMany joining table

There are many possibilities to change default table joining conventions. If you are using `m:m`, you can change its pattern property. By default, the pattern is defined as `%s_x_%s`. The first placeholder is the primary table name.

```php
use Nextras\Orm\Mapper\Dbal\Conventions\Conventions;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\DbalMapper;

/**
 * @template E of \Nextras\Orm\Entity\IEntity
 * @extends DbalMapper<E>
 */
class BaseMapper extends DbalMapper
{
	protected function createConventions(): IConventions
	{
		$conventions = parent::createConventions();
		assert($conventions instanceof Conventions); // property is not available on interface
		$conventions->manyHasManyStorageNamePattern = '%s_2_%s';
		return $conventions;
	}
}
```

If you need more advanced configuration, feel free to override `getManyHasManyParameters()` method in your mapper. This method returns an array where the first value is a joining table name, the second is an array of joining keys/columns. If you have only one `m:m` relationship between two entities, you can return the result based only on the passed target mapper, source property's metadata are available for more detailed matching.

```php
use Nextras\Orm\Mapper\Dbal\DbalMapper;

/**
 * @extends DbalMapper<Employee>
 */
class EmployeesMapper extends DbalMapper
{
	public function getManyHasManyParameters(PropertyMetadata $sourceProperty, DbalMapper $targetMapper): array
	{
		if ($targetMapper instanceof DepartmentsMapper) {
			return ['emp_dept', ['emp_no', 'dept_no']];
		}
		return parent::getManyHasManyParameters($sourceProperty, $targetMapper);
	}
}
```
