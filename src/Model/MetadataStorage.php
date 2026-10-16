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
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_shift;
use function assert;
use function key;


/**
 * Holds the entity metadata.
 *
 * Metadata are loaded lazily: the storage only registers itself as a loader and the metadata
 * are loaded (from cache) on the first {@see MetadataStorage::get()} miss or by {@see MetadataStorage::load()}.
 */
class MetadataStorage
{
	/** @var array<class-string<mixed>, EntityMetadata> */
	private static array $metadata = [];

	/** @var array<int, Closure(): void> */
	private static array $loaders = [];

	private bool $loaded = false;

	private int|null $loaderKey = null;


	public static function get(string $className): EntityMetadata
	{
		while (!isset(self::$metadata[$className]) && self::$loaders !== []) {
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
			if (self::$metadata === []) {
				throw new InvalidStateException("MetadataStorage::get() called too early. You have to instantiate your model first.");
			}
			throw new InvalidArgumentException("Entity metadata for '{$className}' does not exist.");
		}
		return self::$metadata[$className];
	}


	/**
	 * Registers a callback which loads metadata (e.g. by calling {@see MetadataStorage::load()}). Pending loaders
	 * are run one by one on a {@see MetadataStorage::get()} miss, until the requested metadata are found.
	 * Every loader is run at most once (unless it throws). Intended for DI integrations.
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
				self::registerLoader($this->load(...));
				$this->loaderKey = array_key_last(self::$loaders);
				return;
			}
		}

		// all entities are already loaded (e.g. by another instance of the same model), nothing to do
		$this->loaded = true;
	}


	/**
	 * Loads (and validates when not cached yet) metadata of this storage's entities.
	 * Called automatically on the first use.
	 */
	public function load(): void
	{
		if ($this->loaded) return;

		$entityClassesMap = $this->entityClassesMap;
		$metadataParserFactory = $this->metadataParserFactory;
		$repositoryLoader = $this->repositoryLoader;
		$metadata = $this->cache->derive('orm.metadata')->load(
			$entityClassesMap,
			function (&$dp) use ($entityClassesMap, $metadataParserFactory, $repositoryLoader): array {
				/** @var EntityMetadata[] $metadata */
				$metadata = [];
				$toProcess = array_keys($entityClassesMap);
				$annotationParser = $metadataParserFactory->create($entityClassesMap);

				while (($className = array_shift($toProcess)) !== null) {
					$metadata[$className] = $annotationParser->parseMetadata($className, $dp[Cache::Files]);
					foreach ($metadata[$className]->getProperties() as $property) {
						if ($property->wrapper === EmbeddableContainer::class) {
							assert($property->args !== null);
							$toProcess[] = $property->args[EmbeddableContainer::class]['class'];
						}
					}
				}

				$validator = new MetadataValidator();
				$validator->validate($metadata, $repositoryLoader);

				return $metadata;
			},
		);

		/** @var EntityMetadata[] $toProcess */
		$toProcess = $metadata;
		while (($entityMetadata = array_shift($toProcess)) !== null) {
			foreach ($entityMetadata->getProperties() as $property) {
				if ($property->relationship) {
					$property->relationship->entityMetadata = $metadata[$property->relationship->entity];
				}
				if ($property->wrapper === EmbeddableContainer::class) {
					$type = key($property->types);
					$property->args[EmbeddableContainer::class]['metadata'] = $metadata[$type];
					$toProcess[] = $metadata[$type];
				}
			}
		}

		self::$metadata += $metadata;
		$this->loaded = true;

		if ($this->loaderKey !== null) {
			unset(self::$loaders[$this->loaderKey]);
			$this->loaderKey = null;
		}
	}


	/**
	 * Returns all Nextras' metadata loaded in runtime until now, including this storage's metadata.
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
}
