## Migration Guide for 5.0

### BC Breaks

We evaluate here only BC breaks you will probably encounter. This major version consists also of other minor (quite internal) BC breaks, i.e. the probability you don't use these semi-public API is quite high.

- **Generics Annotations** - though they are not necessary for the runtime, for the basic maintenance and development you need to:
  - add generic arguments to all the following types: `Repository`, `Mapper`, `Collection`, `ManyHasMany`, `OneHasMany`,
  - add phpdoc extends with generic arguments to `Mapper` and `Repository`.

    For example, the current state like this:

    ```php
    class UsersRepository extends Repository { ... }
    class UsersMapper extends Mapper { ... }

    /**
     * @param OneHasMany|Book[] $books
     */
    class User extends Entity { ... }
    ```

    has to be modified like this:

    ```php
    /**
     * @extends Repository<User>
     */
    class UsersRepository extends Repository { ... }
    /**
     * @extends DbalMapper<User>
     */
    class UsersMapper extends DbalMapper { ... }

    /**
     * @param OneHasMany<Book> $books
     */
    class User extends Entity { ... }
    ```

- **Removed `Mapper` class**, inherit directly from `Nextras\Orm\Mapper\Dbal\DbalMapper` class (as shown in the previous example). No functional change.

- **Repository::getById()** does not accept IEntity anymore. If you have an entity, explicitly read its id. `->getById($entity->id)`.

- **Removed Repository::findById()**, use `Repository::findByIds()`.

- **Add PHP type to Mapper::$tableName** - when specifying a table name by overriding mapper's property, you have to specify its type (`protected string|Fqn|null $tableName`).

- **Custom Collection functions** interfaces were merged and API was changed. Implement both methods from the new `Nextras\Orm\Collection\Functions\CollectionFunction` interface and follow how-to tutorial in [Collection Functions](collection-functions).

- Removed various already deprecated errors.


### Nextras Dbal Changes

The Orm 5.0 requires Dbal 5.0, see [its release notes](https://github.com/nextras/dbal/releases/tag/v5.0.0).
