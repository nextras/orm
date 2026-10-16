<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Closure;
use Nette\Caching\Cache;
use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataValidator;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Repository\IRepository;
use Throwable;
use function array_fill_keys;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_merge;
use function array_shift;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function is_array;
use function key;
use function md5;
use function serialize;


/**
 * Holds the entity metadata.
 *
 * Metadata are loaded lazily and per entity: the storage only registers itself and the metadata of an entity
 * (and of its embeddables) are loaded from cache on the first {@see MetadataStorage::get()} miss.
 * Metadata of related entities are resolved on the first access to
 * {@see \Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata::$entityMetadata}.
 * Parsing and validation of all entities happen only when the cache is (re)built.
 */
class MetadataStorage
{
	/** @var array<class-string<mixed>, EntityMetadata> */
	private static array $metadata = [];

	/** @var array<int, Closure(): void> */
	private static array $loaders = [];

	/** @var array<int, MetadataStorage> storages which may provide not yet loaded metadata */
	private static array $pending = [];

	/** @var array<string, true>|null class names (entities and embeddables) provided by this storage */
	private array|null $index = null;

	/** @var array<string, true> */
	private array $loadedClasses = [];

	private int|null $pendingKey = null;

	private Cache|null $metadataCache = null;

	private string|null $cacheKey = null;


	public static function get(string $className): EntityMetadata
	{
		while (!isset(self::$metadata[$className])) {
			foreach (self::$pending as $storage) {
				if ($storage->loadClass($className)) {
					return self::$metadata[$className];
				}
			}

			if (self::$loaders === []) break;

			$key = array_key_first(self::$loaders);
			$loader = self::$loaders[$key];
			unset(self::$loaders[$key]);
			try {
				$loader();
			} catch (Throwable $e) {
				// keep the loader so the next call reports the same error instead of "called too early"
				self::$loaders = [$key => $loader] + self::$loaders;
				throw $e;
			}
		}

		if (!isset(self::$metadata[$className])) {
			if (self::$metadata === [] && self::$pending === []) {
				throw new InvalidStateException("MetadataStorage::get() called too early. You have to instantiate your model first.");
			}
			throw new InvalidArgumentException("Entity metadata for '{$className}' does not exist.");
		}
		return self::$metadata[$className];
	}


	/**
	 * Registers a callback which creates a metadata storage (e.g. by calling {@see MetadataStorage::register()}).
	 * Pending loaders are run one by one on a {@see MetadataStorage::get()} miss, until the requested
	 * metadata are found. Every loader is run at most once (unless it throws). Intended for DI integrations.
	 * @param Closure(): void $loader
	 */
	public static function registerLoader(Closure $loader): void
	{
		self::$loaders[] = $loader;
	}


	/**
	 * @param array<class-string<IEntity>, class-string<IRepository<IEntity>>> $entityClassesMap
	 */
	public function __construct(
		private readonly array $entityClassesMap,
		private readonly Cache $cache,
		private readonly IMetadataParserFactory $metadataParserFactory,
		private readonly IRepositoryLoader $repositoryLoader,
	)
	{
		foreach ($entityClassesMap as $className => $_) {
			if (!isset(self::$metadata[$className])) {
				self::$pending[] = $this;
				$this->pendingKey = array_key_last(self::$pending);
				return;
			}
		}
		// all entities are already loaded (e.g. by another instance of the same model), nothing to do
	}


	/**
	 * Returns true if some of this storage's metadata have not been loaded yet. Calling it on a lazy ghost
	 * (Nette DI's lazy services) initializes the service, i.e. registers the storage for lazy loading.
	 */
	public function register(): bool
	{
		return $this->pendingKey !== null;
	}


	/**
	 * Loads (and validates when not cached yet) metadata of all this storage's entities.
	 */
	public function load(): void
	{
		if ($this->pendingKey === null) return;
		foreach ($this->getIndex() as $className => $_) {
			$this->loadClass($className);
		}
	}


	/**
	 * Returns all Nextras' metadata loaded in runtime until now, including all this storage's metadata.
	 *
	 * In other words, two independent ORM instances share the static storage, and the data
	 * returned in this method are only those that have been already loaded.
	 *
	 * @return array<class-string<mixed>, EntityMetadata>
	 */
	public function getLoadedMetadata(): array
	{
		$this->load();
		return self::$metadata;
	}


	/**
	 * Loads metadata of the class if this storage provides them.
	 */
	private function loadClass(string $className): bool
	{
		$index = $this->getIndex();
		if (!isset($index[$className])) return false;

		if (!isset(self::$metadata[$className])) {
			$metadata = $this->getCache()->load($this->getCacheKey() . '.' . $className);
			if (!$metadata instanceof EntityMetadata) {
				$metadata = $this->build()[$className];
			}

			foreach ($metadata->getProperties() as $property) {
				if ($property->wrapper === EmbeddableContainer::class) {
					$type = key($property->types);
					assert($type !== null);
					$property->args[EmbeddableContainer::class]['metadata'] = self::get((string) $type);
				}
			}

			/** @var class-string $className */
			self::$metadata[$className] ??= $metadata;
		}

		$this->loadedClasses[$className] = true;
		if (count($this->loadedClasses) === count($index) && $this->pendingKey !== null) {
			unset(self::$pending[$this->pendingKey]);
			$this->pendingKey = null;
		}
		return true;
	}


	/**
	 * @return array<string, true>
	 */
	private function getIndex(): array
	{
		if ($this->index === null) {
			$index = $this->getCache()->load($this->getCacheKey() . '.index');
			if (is_array($index)) {
				/** @var array<string, true> $index */
				$this->index = $index;
			} else {
				$this->index = array_fill_keys(array_keys($this->build()), true);
			}
		}
		return $this->index;
	}


	/**
	 * Parses and validates all entities and stores each entity's metadata into a separate cache item,
	 * together with an index of all provided class names.
	 * @return array<string, EntityMetadata>
	 */
	private function build(): array
	{
		/** @var array<string, EntityMetadata> $metadata */
		$metadata = [];
		/** @var array<string, list<string>> $files */
		$files = [];
		$toProcess = array_keys($this->entityClassesMap);
		$annotationParser = $this->metadataParserFactory->create($this->entityClassesMap);

		while (($className = array_shift($toProcess)) !== null) {
			if (isset($metadata[$className])) continue;
			$fileDependencies = [];
			$metadata[$className] = $annotationParser->parseMetadata($className, $fileDependencies);
			$files[$className] = $fileDependencies ?? [];
			foreach ($metadata[$className]->getProperties() as $property) {
				if ($property->wrapper === EmbeddableContainer::class) {
					assert($property->args !== null);
					$toProcess[] = $property->args[EmbeddableContainer::class]['class'];
				}
			}
		}

		$validator = new MetadataValidator();
		$validator->validate($metadata, $this->repositoryLoader);

		$cache = $this->getCache();
		$cacheKey = $this->getCacheKey();
		foreach ($metadata as $className => $entityMetadata) {
			$cache->save($cacheKey . '.' . $className, $entityMetadata, [Cache::Files => $files[$className]]);
		}
		$cache->save($cacheKey . '.index', array_fill_keys(array_keys($metadata), true), [
			Cache::Files => array_values(array_unique(array_merge(...array_values($files)))),
		]);

		return $metadata;
	}


	private function getCache(): Cache
	{
		return $this->metadataCache ??= $this->cache->derive('orm.metadata');
	}


	private function getCacheKey(): string
	{
		return $this->cacheKey ??= md5(serialize($this->entityClassesMap));
	}
}
