## Configuration in Nette DI

Orm comes with `OrmExtension` that will help you integrate all needed services with [Nette\DI](http://doc.nette.org/en/dependency-injection) component.

#### PhpDoc Repository Definition

The most common use-case is to define repositories as model class PhpDoc annotations. Orm extension will take care of your repositories and automatically creates their definition for DI container. Also, a lazy loader will be injected into the model. The loader will provide repositories directly from your DI container.

To define model repository use PhpDoc `@property-read` annotation:

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

The key `model` accepts a class name of your project's model. Access your repositories via magic getter or let them wire by DIC:

```php
$orm = $dic->getByType(Model::class); // or auto-wire
$orm->posts->findAll();

$postsRepository = $dic->getByType(PostsRepository::class); // or auto-wire
$postsRepository->findAll();
```

#### DI Repository Definition

You may want to define all your repositories (dynamically) in your DIC. Orm provides a different repository finder for such use-case. Orm will not create any other DIC's repository definitions and will reuse all `IRepository` instances in your DIC config. When using DIRepositoryFinder, do not define your own model and use `Nextras\Orm\Model\Model` if needed.

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

Repositories are registered also with their names that are generated from the repository classname. If you want a different behavior, you may override `DIRepositoryFinder::getRepositoryName()` method.


#### Customizations

By default, Orm classes utilize a Cache service. You may redefine your own:

```neon
services:
	nextras.orm.cache: Cache(..., 'mynamespace')
```

To parse own modifiers add `addModifier` call to parser factory's setup or define your metadata parser factory from scratch:

```neon
services:
	nextras.orm.metadataParserFactory:
		setup:
			- addModifier(modifier, [@myservice, parseMethod])
```

Orm allows injecting dependencies into your entities. This is dependency provider responsibility, feel free provide custom implementation:

```neon
services:
	nextras.orm.dependencyProvider: MyApp\DependencyProvider
```
