## Entity Embeddables

Embeddables are not entities themselves but rather wrap a few properties and are embedded in entities. Using embeddables helps to separate concerns, reuse code, and design more secure architecture since embeddables are immutable by design.

Embeddable wraps properties into its own data class. The embeddable's nullability and its properties' nullability are derived from PHPDoc types. Embeddable must inherit from the `Nextras\Orm\Entity\Embeddable\Embeddable` abstract class. Properties of an embeddable are defined the same way as for an entity.

Initializing an embeddable object uses a constructor which accepts an array with keys as property names and appropriate values. However, you can override the constructor or add other methods to provide a more convenient way of creating an embeddable object.

In the following example, we join multiple address fields into one unified (and reusable) address data class. If all address properties are `null`, Orm does not instantiate the embeddable but puts null into `$address`.

```php
/**
 * @property Address|null $address {embeddable}
 */
class User extends Nextras\Orm\Entity\Entity
{
}

/**
 * @property string $street
 * @property string $city
 * @property string|null $state
 * @property string $country
 * @property string $zipCode
 */
class Address extends Nextras\Orm\Entity\Embeddable
{
	public function __construct(string $street, string $city, ?string $state, string $country, string $zipCode)
	{
		parent::__construct([
			'street' => $street,
			'city' => $city,
			'state' => $state,
			'country' => $country,
			'zipCode' => $zipCode,
		]);
	}
}
```

The example by default stores data in the `address_street` column, etc. You may also filter by the nested structure. This works for both array and dbal collections.

```php
$users = $orm->users->findBy(['address->city' => 'Prague']);
```

Setting values requires creating a new embeddable instance by yourself. If you want to change its property, create a new one and set it again.

```php
$user = new User();
$user->address = new Address('Main st.', 'Prague', null, 'Czechia', '10000');
echo $user->address->street;
```


#### Conventions

As always, you may want to change the default embeddable mapping to database column names. Just use `->` as a nested separator for the entity property name and use the [usual API](conventions).

```php
protected function createConventions(): IConventions
{
	$conventions = parent::createConventions();
	$conventions->setMapping('address->zipCode', 'address_postal_code');
	return $conventions;
}
```

Currently, there is no easy way to change the prefix for all embeddable properties, just enumerate all the properties with a new column name.

Use `$embeddableSeparatorPattern` to change the default separator between holding & nested property.

```php
protected function createConventions()
{
	$conventions = parent::createConventions();
	$conventions->embeddableSeparatorPattern = '__';
	return $conventions;
}
```

The example will generate `address__street`, etc.
