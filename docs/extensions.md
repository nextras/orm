## Extensions

An extension is an entry point for hooking into Orm's setup. It lets bundled or third-party packages consistently configure the model, repositories, mappers, and entity metadata without patching your application code. Typical use cases:

- registering custom metadata modifiers or reacting to parsed entity metadata (e.g. adding behavior based on a custom property modifier),
- attaching event callbacks to every repository,
- injecting shared dependencies or configuration into mappers.

To implement an extension, extend the `Nextras\Orm\Extension` abstract class and override only the methods you need. All methods have an empty default implementation.

```php
use Nextras\Orm\Extension;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;

class MyExtension extends Extension
{
	public function configureModel(IModel $model): void
	{
		$model->onFlush[] = function ($persisted, $removed) {
			// ...
		};
	}

	public function configureRepository(IRepository $repository): void
	{
		$repository->onBeforePersist[] = function ($entity) {
			// ...
		};
	}

	// e.g. assign a custom property wrapper to every property of a given type
	public function configureEntityPropertyMetadata($entityMetadata, $propertyMetadata, $propertyType): void
	{
		if ($propertyMetadata->wrapper !== null) return;
		foreach ($propertyMetadata->types as $type => $_) {
			if ($type === LocalDate::class || is_subclass_of($type, LocalDate::class)) {
				$propertyMetadata->wrapper = LocalDatePropertyWrapper::class;
			}
		}
	}
}
```

#### Hooks

| Method                                                                                                                        | Runs                                                                                 |
|-------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------|
| `configureModel(IModel $model)`                                                                                               | Once, when the model is instantiated.                                                |
| `configureRepository(IRepository $repository)`                                                                                | Every time a repository is instantiated at runtime.                                  |
| `configureMapper(IMapper $mapper)`                                                                                            | Every time a mapper is instantiated at runtime.                                      |
| `configureEntityMetadata(EntityMetadata $metadata)`                                                                           | At compile time, when entity metadata is parsed (before it is cached).               |
| `configureEntityPropertyMetadata(EntityMetadata $entityMetadata, PropertyMetadata $propertyMetadata, TypeNode $propertyType)` | At compile time, per property, when entity metadata is parsed (before it is cached). |

The runtime hooks (`configureModel`, `configureRepository`, `configureMapper`) run each time the respective service is created, so keep them cheap. The compile-time hooks (`configureEntityMetadata`, `configureEntityPropertyMetadata`) run only while metadata is parsed and their result is serialized into the metadata cache — they do **not** run again on subsequent requests served from the cache. Because of that, do not capture request-specific state in them.

#### Registration with Nette DI

Register extensions through the `extensions` option of the [Nette DI](config-nette) `OrmExtension`. Each entry may be:

- a class name — a new service is registered and instantiated once,
- a `Nette\DI\Definitions\Statement` — a factory for a new service,
- a `@reference` — an already registered service is reused.

```neon
nextras.orm:
	model: MyApp\Model
	extensions:
		- MyApp\MyExtension
		- MyApp\ConfigurableExtension(%myOption%)
		- @myAlreadyRegisteredExtension
```

Each extension is instantiated once and shared across the metadata parser, the model, and every repository.

#### Registration without Nette DI

When bootstrapping the model manually via `Nextras\Orm\Model\SimpleModelFactory`, pass the extension instances as the last constructor argument:

```php
use Nextras\Orm\Model\SimpleModelFactory;

$factory = new SimpleModelFactory(
	$cache,
	$repositories,
	extensions: [new MyApp\MyExtension()],
);
$model = $factory->create();
```
