## Migration Guide for 6.0

### BC Breaks

- **`IProperty::setRawValue()` must not validate the value** - the method now only stores the passed raw value; it must neither convert it to the runtime representation nor validate it. Both the conversion and any validation are deferred to read time (`getInjectedValue()` / `getRawValue()`).

  This fixes premature initialization of non-nullable wrapped properties — reading such a property via `IEntity::getProperty()`, or creating an entity through `EntityCreator`, no longer triggers validation before the actual value is set.

  If you maintain a **custom property wrapper**:
  - Any validation previously performed in `setRawValue()` must be moved to read time — `setRawValue()` may be called with a value that is not yet known to be valid (or that is about to be overwritten before it is ever read).
  - `Nextras\Orm\Entity\ImmutableValuePropertyWrapper` already implements this: it keeps the raw value, converts it lazily via `convertFromRawValue()` on read, and validates nullability centrally. Your `convertFromRawValue()` is free to validate the (converted) value as before — it is simply invoked lazily now.

  As a consequence, an invalid raw value now surfaces its exception when the value is **read** rather than when it is **set** (i.e. during hydration / `setRawValue()`).

- **`Model` constructor changed & `Model::getConfiguration()` removed** - the repository configuration array is gone. The model now delegates all repository lookups to its `IRepositoryLoader`.

  ```php
  // before
  new Model($configuration, $repositoryLoader, $metadataStorage);
  Model::getConfiguration($repositories); // removed

  // after
  new Model($repositoryLoader, $metadataStorage);
  ```

  This affects you only if you instantiate `Model` manually (i.e. not via the Nette DI extension or `SimpleModelFactory`). The entity-to-repository and name-to-repository maps are now owned by the loader (see below).

- **`IRepositoryLoader` interface reworked** - if you maintain a custom repository loader, update it to the new contract:
  - `getRepository()` now returns `IRepository|null` (previously non-nullable). The `Model` throws when it receives `null`.
  - `isCreated()` was **removed**; implement `getInitializedRepositories(): list<IRepository<*>>` instead (returns the already-instantiated repositories, used by `flush()` / `clear()` / `refreshAll()`).
  - Added `hasRepositoryByName(string $name): bool` and `getRepositoryByName(string $name): IRepository|null`.
  - Added `getRepositoryClassNameForEntity(string $entityClassName): ?string` - resolves the repository managing a given entity class (previously the `Model` held this map itself).

- **Removed `Nextras\Orm\Bridges\NetteDI\RepositoryLoader`** - replaced by `Nextras\Orm\Bridges\NetteDI\DiRepositoryLoader`, which implements the new `IRepositoryLoader` contract and reads repositories lazily from the Nette DI container.

- **`IRepositoryFinder` interface reworked** - if you implement a custom repository finder for the Nette DI bridge:
  - Constructor arguments were reordered to `__construct(ContainerBuilder $builder, OrmExtension $extension, string $modelClass)`.
  - `loadConfiguration(): ?array` was replaced by `registerRepositories(): void` (register your repository service definitions here).
  - `beforeCompile(): ?array` was replaced by `resolveRepositories(): list<DiRepositoryEntry>` (return the resolved repository service definitions; the `OrmExtension` wires them to the model). See the new `Nextras\Orm\Bridges\NetteDI\DiRepositoryEntry` value object.

- **`MetadataParserFactory` constructor gained `$extensions`** - it now accepts `list<Nextras\Orm\Extension>` as its first constructor argument and forwards them to the `MetadataParser`. If you register a custom `nextras.orm.metadataParserFactory` service or construct the factory manually, account for the new argument.

- **`SimpleModelFactory` / `SimpleRepositoryLoader` signatures changed** (relevant for non-Nette, manual model bootstrapping):
  - `SimpleModelFactory::__construct()` gained a trailing `array $extensions = []` parameter (`list<Extension>`).
  - `SimpleRepositoryLoader::__construct()` gained a second `array $entityClassNameToClassNameMap = []` parameter (map of entity class name → managing repository class name), needed for `getRepositoryClassNameForEntity()`.

### New Features

- **`Nextras\Orm\Extension` entry point** - a new abstract class that lets bundled or third-party packages hook into the Orm setup. Override any of the methods you need and register the extension:

  ```php
  class MyExtension extends \Nextras\Orm\Extension
  {
      public function configureModel(IModel $model): void { /* ... */ }
      public function configureRepository(IRepository $repository): void { /* ... */ }
      public function configureMapper(IMapper $mapper): void { /* ... */ }
      public function configureEntityMetadata(EntityMetadata $metadata): void { /* ... */ }
      public function configureEntityPropertyMetadata(
          EntityMetadata $entityMetadata,
          PropertyMetadata $propertyMetadata,
          TypeNode $propertyType,
      ): void { /* ... */ }
  }
  ```

  With the Nette DI bridge, register extensions through the `extensions` option. Each entry may be a class name, a `Nette\DI\Definitions\Statement`, or a `@reference` to an already registered service:

  ```neon
  nextras.orm:
      model: MyApp\Model
      extensions:
          - MyApp\MyExtension
          - @myAlreadyRegisteredExtension
  ```

  The `configureModel`/`configureRepository`/`configureMapper` hooks run when the respective service is instantiated; the `configureEntityMetadata`/`configureEntityPropertyMetadata` hooks run at compile time while entity metadata is parsed (before it is cached).
