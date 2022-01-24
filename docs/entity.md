## Entity

Entity is a data crate which, basically, contains data for one table row. Each entity has to implement `Nextras\Orm\Entity\IEntity` interface. Orm has predefined class `Nextras\Orm\Entity\Entity`, which implements the interface and provides other useful features.

Data is accessible through properties. You have to annotate all properties that should be available. Properties are defined by Phpdoc annotations. Let's start with a basic entity:

```php
/**
 * @property int               $id {primary}
 * @property string            $name
 * @property DateTimeImmutable $born
 * @property string|null       $web
 * @property-read int          $age
 */
class Member extends Nextras\Orm\Entity\Entity
{
}
```

Phpdoc property definition consists of its type and name. If you would like to use read-only property, define it with `@property-read` annotation; such annotation is useful to define properties which are based on values of other properties. Properties could be optional/nullable; to do that, just provide another type - `null` or you could use it by prefixing the type name with a question mark - `?string`.

If you put some value into the property, the value will be validated by property type annotation. Type casting is performed if it is possible and safe. Supported types are `null`, `string`, `int`, `float`, `array`, `mixed` and object types. Validation is provided on all properties, except for properties defined with property wrapper - in that case validation should do its property wrapper.

Nextras Orm also provides enhanced support for date time handling. However, only "safe" `DateTimeImmutable` instances are supported as a property type. You may put a common `DateTime` instance as a value, but it will be automatically converted to DateTimeImmutable. Also, auto date string conversion is supported.

"Property access" is the easiest way to work with the data, although such feature is not defined in `IEntity` interface. To conform to the interface, you must use "method access": `getValue()` method for reading, `setValue()` method for writing, `hasValue()`, etc. There is a special `getRawValue()` method, which returns a raw representation of the value. The raw representation is basically the stored value (a primary key for relationship property).

```php
$member = new Member();

$member->name = 'Jon';
$member->setValue('name', 'Jon');
$member->born = 'now'; // will be automatically converted to DateTimeImmutable

echo $member->name;
echo $member->getValue('name');

echo isset($member->web) ? 'has web' : '-';
echo $member->hasValue('web') ? 'has web' : '-';

$member->isPersisted(); // false
```

Attaching entities to the repository lets Orm know about your entities. Attaching to a repository runs the required dependencies injection into your entity (through inject property annotations or inject methods). If you need some dependency before attaching entity to the repository, feel free to pass the dependency via the constructor, which is by default empty.

Each entity can be created "manually". Entities can be simply connected together. Let's see an example:

```php
$author = new Author();

$book = new Book();
$book->author = $author;
$book->tags->set([new Tag(), new Tag()]);
```

If an Author instance is attached to the repository, all other new connected entities are automatically attached to their repositories too. See more in [relationships chapter](relationships).

#### Getters and setters

Entity allows you to implement own getters and setters to modify the passed value. These methods are optional and should be defined as `protected`. The method name consists of the `getter` prefix, and a property name, `setter` prefix respectively. You can define just one of them. Getters and setters are not supported for property wrappers (e.g. that relationships cannot have them).

Getter method receives the stored value as the first parameter and returns the desired value. Setter method receives the user given value and returns the desired value for storing it in the entity. Getters of virtual properties do not receive any value (always receive a null value).

```php
/**
 * ...
 * @property string $name
 * @property int    $siblingsCount
 */
class FamilyMember extends Entity
{
	protected function getterName(string $name): string
	{
		return ucwords($name);
	}

	protected function setterSiblingsCount(int $siblings): int
	{
		return max((int) $siblings, 0);
	}
}
```

### Property modifiers

Each property can be annotated with a modifier. Modifiers are optional and provide a possibility to extend entity properties' behavior. Modifiers are written after the property name. Each modifier is surrounded by curly braces. The first compulsory token is the modifier name, other tokens are optional and depend on the specific modifier type. Orm comes with few predefined property modifiers:

- `{primary}`                                - makes the property mapped as a primary key;
- `{primary-proxy}`                          - makes the property a proxy to map the primary key; useful for composite primary key easy access;
- `{default now}`                            - defines a default value;
- `{virtual}`                                - marks property as "do not persist in storage";
- `{embeddable}`                             - encapsulates multiple properties into one wrapping object;
- `{wrapper PropertyWrapperClassName}`       - sets property wrapper;
- `{enum self::TYPE_*}`                      - enables extended validation against values enumeration; we recommend using object enum types instead of scalar enum types;
- `{1:m TargetEntity::$property}`            - see [relationships](relationships).
- `{m:1 TargetEntity::$property}`            - see [relationships](relationships).
- `{m:m TargetEntity::$property}`            - see [relationships](relationships).
- `{1:1 TargetEntity::$property}`            - see [relationships](relationships).

#### `{primary}` and `{primary-proxy}`

Each entity has to have defined the `$id` property.
By default, the `$id` property is the only primary key of the entity; the `$id` property is defined in `Nextras\Orm\Entity\Entity` class, but it is not marked as primary, because this is the default behavior, which can be changed by the `{primary}` modifier. By adding the modifier to property, you mark it as the new primary key. You can use the modifier multiple times to create a composite primary key. If the modifier is applied to a relationship property, the relationship's primary key is automatically used.

```php
/**
 * @property int    $id       {primary}
 * @property string $name
 */
class Tag extends Nextras\Orm\Entity\Entity
{
}

/**
 * @property mixed  $id        {primary-proxy}
 * @property Tag    $tag       {m:1 Tag::$followers} {primary}
 * @property User   $follower  {m:1 User::$followedTags} {primary}
 */
class TagFollower extends Nextras\Orm\Entity\Entity
{
}


$tag = new Tag();
$tag->id = 1;

$user = new User();
$user->id = 2345;

$tagFollower = new TagFollower();
$tagFollower->tag = $tag;
$tagFollower->user = $user;

return $tagFollower->id;
// returns array(1, 2345);
```

#### `{default}`

You can easily set the default value. The default modifier also accepts a reference to constant.

```php
/**
 * ...
 * @property string  $name   {default "Jon Snow"}
 * @property int     $type   {default self::TYPE_PUBLIC}
 */
class Event extends Nextras\Orm\Entity\Entity
{
	const TYPE_PUBLIC = 0;
}
```

#### `{virtual}`

Virtual modifier marks specific property as virtual - such property won't be stored in the mapper; this modifier is useful to use with `property-read` annotation together.

```php
/**
 * ...
 * @property      DateTimeImmutable $born
 * @property-read int               $age {virtual}
 */
class Member extends Nextras\Orm\Entity\Entity
{
	protected function getterAge()
	{
		return date('Y') - $this->born->format('Y');
	}
}

$member = new Member();
$member->born = new DateTimeImmutable('2000-01-01');
echo $member->age;
```

#### `{embeddable}`

Encapsulates multiple properties into a wrapper container. Read more in [Embeddables tutorial](embeddables).

```php
/**
 * @property-read int     $cents
 * @property-read string  $currency
 */
class Money extends Embeddable
{
}

/**
 * ...
 * @property Money|null $price {embeddable}
 */
class Product extends Nextras\Orm\Entity\Entity
{
}
```

#### `{wrapper}` -- property wrappers

Property wrapper encapsulates a property value. There are few basic types of property wrappers:

- **IProperty** - basic wrapper that implements `Nextras\Orm\Entity\IProperty` interface; reading  property retrieves the wrapper object, writing into the wrapper is forbidden.
 *This wrapper is used in "has many" relationships. Reading the property value returns a wrapper object that holds the relationship.*

- **IPropertyContainer** - fully encapsulates its value; setting a value to the property proxies the value into the wrapper by calling `setInjectedValue()` method; reading value from the property is proxied to `getInjectedValue()` wrapper's method.
 *This feature is used in "has one" relationships. The property value goes through wrapper, user receives/sets the property wrapper inner state.*

Property wrappers are created by entity and lazily.

```php
/**
 * ...
 * @property \stdClass $json {wrapper JsonWrapper}
 */
class Data extends Entity
{
}

class JsonWrapper extends ImmutableValuePropertyWrapper
{
    public function convertToRawValue($value)
	{
		return json_encode($value);
	}

	public function convertFromRawValue($value)
	{
		return json_decode($value);
	}
}
```

#### `{enum}`

You can easily validate passed value by value enumeration. To set the enumeration validation, use `enum` modifier with the list of constants (separated by a space); or pass a constant name with a wildcard.

```php
/**
 * ...
 * @property int $type {enum self::TYPE_*}
 */
class Event extends Nextras\Orm\Entity\Entity
{
	const TYPE_PUBLIC  = 0;
	const TYPE_PRIVATE = 1;
	const TYPE_ANOTHER = 2;
}
```

### Entity dependencies

Your entity can require some dependency to work. Orm comes with `Nextras\Orm\Repository\IDependencyProvider` interface, which takes care about injecting needed dependencies. If you use `OrmExtension` for `Nette\DI`, it will automatically call standard DI injections (injection methods and `@inject` annotation). Dependencies are injected when an entity is attached to the repository.

```php
/**
 * ...
 */
class Book extends Nextras\Orm\Entity\Entity
{
	/** private EanSevice */
	private $eanService;

	public function injectEanService(EanService $service)
	{
		$this->eanService = $service;
	}
}
```
