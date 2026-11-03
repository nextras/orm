## Configuration in Nette DI

Orm comes with `OrmExtension` that will help you integrate all needed services with the [Nette\DI](http://doc.nette.org/en/dependency-injection) component.

#### PhpDoc Repository Definition

The most common use case is to define repositories as model class PhpDoc annotations. Orm extension will take care of your repositories and automatically create their definitions for the DI container. Also, a lazy loader will be injected into the model. The loader will provide repositories directly from your DI container.

To define a model repository, use the PhpDoc `@property-read` annotation:

```php
namespace MyApp;

/**
 * @property-read PostsRepository $posts
 * @property-read UsersRepository $users
 * @property-read TagsRepository $tags
 */
class Model extends \Nextras\Orm\Model\Model
{
}
```

Then configure Orm extension in your application `config.neon`:

```neon
extensions:
	nextras.orm: Nextras\Orm\Bridges\NetteDI\OrmExtension

nextras.orm:
	model: MyApp\Model
```

The key `model` accepts the class name of your project's model. Access your repositories via magic getter or let them be wired by DIC:

```php
$orm = $dic->getByType(Model::class); // or auto-wire
$orm->posts->findAll();

$postsRepository = $dic->getByType(PostsRepository::class); // or auto-wire
$postsRepository->findAll();
```

#### DI Repository Definition

You may want to define all your repositories (dynamically) in your DIC. Orm provides a different repository finder for such a use case. Orm will not create any other DIC repository definitions and will reuse all `IRepository` instances in your DIC config. When using DIRepositoryFinder, do not define your own model and use `Nextras\Orm\Model\Model` if needed.

```neon
extensions:
	nextras.orm: Nextras\Orm\Bridges\NetteDI\OrmExtension

nextras.orm:
	repositoryFinder: Nextras\Orm\Bridges\NetteDI\DIRepositoryFinder

services:
	- MyApp\PostsRepository(MyApp\PostsMapper())
```

```php
namespace MyApp;

class MyService
{
	/** @var Orm */
	private $orm;

	public function __construct(Orm $orm)
	{
		$this->orm = $orm;
	}

	public function doSomething($postId)
	{
		$post = $this->orm->getRepository(PostsRepository::class)->getById($postId);
		// ...
	}
}
```

Repositories are also registered with their names that are generated from the repository classname. If you want different behavior, you may override the `DIRepositoryFinder::getRepositoryName()` method.


#### Customizations

By default, Orm classes use a Cache service. You may redefine your own:

```neon
services:
	nextras.orm.cache: Cache(..., 'mynamespace')
```

To parse custom modifiers, add an `addModifier` call to the parser factory's setup or define your metadata parser factory from scratch:

```neon
services:
	nextras.orm.metadataParserFactory:
		setup:
			- addModifier(modifier, [@myservice, parseMethod])
```

Orm allows injecting dependencies into your entities. This is the dependency provider's responsibility; feel free to provide a custom implementation:

```neon
services:
	nextras.orm.dependencyProvider: MyApp\DependencyProvider
```

To hook into the model, repository, mapper, or entity metadata setup, register an [extension](extensions) through the `extensions` option:

```neon
nextras.orm:
	extensions:
		- MyApp\MyExtension
		- @myAlreadyRegisteredExtension
```

Entity metadata are loaded lazily and per entity, on their first use (e.g. a standalone `new Post()` or the first repository query), so requests pay only for the entities they use. This works the same with Nette DI's lazy services (`di: lazy: true`, PHP 8.4+), no configuration is needed.

Set `initializeMetadata: false` to opt out: metadata are then available only after the model's metadata storage has been created; until then, a standalone `new Post()` throws `MetadataStorage::get() called too early`. Do not combine it with Nette DI's lazy services: the metadata storage is then injected as a lazy ghost which is never created, so every Orm operation fails with this error; the extension triggers an `E_USER_WARNING` during the container compilation in this case. The `initializeMetadata: true` value is deprecated and behaves the same as the default (it used to load all metadata when the container was initialized); to load all metadata ahead of time, see [Metadata Cache Warm-up](#toc-metadata-cache-warm-up).

Orm sets up all internal services as autowired. This may be toggled by the `autowiredInternalServices` option. This may be useful, especially when the Orm extension is used multiple times. The `connection` option allows specifying the related connection instance.

```neon
extensions:
    nextras.orm1: Nextras\Orm\Bridges\NetteDI\OrmExtension
    nextras.orm2: Nextras\Orm\Bridges\NetteDI\OrmExtension
    nextras.dbal1: Nextras\Dbal\Bridges\NetteDI\DbalExtension
    nextras.dbal2: Nextras\Dbal\Bridges\NetteDI\DbalExtension

nextras.orm1:
    model: MainModel
    connection: @nextras.dbal1.connection

nextras.orm2:
    model: AnotherModel
    connection: @nextras.dbal2.connection
    autowiredInternalServices: false
```

#### Metadata Cache Warm-up

When the metadata cache is empty (e.g. right after a deployment), the first request using Orm parses and validates all entities and writes the cache. Call `MetadataStorage::load()` from your deploy script to do this work ahead of time; with a warm cache it only reads the cached metadata. It needs neither the model nor a database connection, and invalid metadata (e.g. a missing symmetric relationship) make it throw, so the deployment fails instead of the first request.

```php
// e.g. bin/warmup-orm.php, run after clearing temp/cache
$configurator = App\Bootstrap::boot(); // adjust to your app's bootstrap returning Nette\Bootstrap\Configurator
$container = $configurator->createContainer();
$container->getByType(Nextras\Orm\Model\MetadataStorage::class)->load();
```

With Nette DI's lazy services, the call initializes the lazy metadata storage service. With multiple Orm extensions or `autowiredInternalServices: false`, get each storage by its service name, e.g. `$container->getService('nextras.orm1.metadataStorage')->load()`. Without Nette DI, call `$model->getMetadataStorage()->load()` on the model created by `SimpleModelFactory`.

- Run the warm-up as the same OS user and with the same temp directory (cache storage) as the web server, otherwise the web server neither sees nor can rewrite the cache.
- Clear the cache before the warm-up; the cache key is derived from the entity map, so cache items of previous versions are never removed.
- Cached metadata depend on the entity source files; when a file changes, the next request rebuilds the cache anyway.
- The warm-up is pointless with a non-persistent cache storage (e.g. `DevNullStorage`). The deprecated `initializeMetadata: true` option does not warm anything up.
