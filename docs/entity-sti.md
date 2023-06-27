## Entity STI - Single Table Inheritance

Orm supports single-table-inheritance to provide more advanced architecture. Although Orm supports this feature, we still see this design as quite rare and should not be overused or misused.

#### Definition

1. Create an abstract general predecessor of your entities that will define all shared properties. The children entities have to inherit from it.
2. Register your abstract entity and all other entities in repository's `getEntityClassNames()`. The common abstract entity has to be registered as the first one.
3. Override `Repository::getEntityClassName(array $data)` to detect the entity class name for the specific row.

Let's take a look at example:

```php
/**
 * @property int $id {primary}
 * @property string $type {enum self::TYPE_*}
 */
abstract class Address extends Nextras\Orm\Entity\Entity
{
	const TYPE_PUBLIC = 'public';
	const TYPE_PRIVATE = 'private';
}

class PrivateAddress extends Address
{
}

/**
 * @property Maintainer $maintainer {m:1 Maintainer::$addressees}
 */
class PublicAddress extends Address
{
}

/**
 * @extends Nextras\Orm\Repository\Repository<Address>
 */
class AddressesRepository extends Nextras\Orm\Repository\Repository
{
	public static function getEntityClassNames(): array
	{
		return [Address::class, PrivateAddress::class, PublicAddress::class];
	}

	public function getEntityClassName(array $data): string
	{
		return $data['type'] === Address::TYPE_PUBLIC ? PublicAddress::class : PrivateAddress::class;
	}
}
```

#### Usage

Collection calls will by default return a mixed result -- with both types. You may filter these collections by the common properties defined on the abstract class. If you want to filter by property that is not shared between all entities, it's your responsibility to filter the proper entities first. To access non-shared properties, prepend a class name with double colon into the expression path.

```php
$orm->addresses->findBy([
	'type' => Address::TYPE_PUBLIC,
	'PublicAddress::maintainer->id' => $maintainerId,
]);
```

The relationship itself point to a specific entity name, so the filtering expression will be evaluated deterministically. Only the starting class name has to be defined, if the property is not shared.
