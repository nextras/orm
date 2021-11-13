<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nette\Caching\Cache;
use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataValidator;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use function array_keys;
use function array_shift;
use function assert;
use function key;


class MetadataStorage
{
	/** @var EntityMetadata[] */
	private static $metadata = [];


	public static function get(string $className): EntityMetadata
	{
		if (!isset(self::$metadata[$className])) {
			if (self::$metadata === []) {
				throw new InvalidStateException("MetadataStorage::get() called too early. You have to instantiate your model first.");
			}
			throw new InvalidArgumentException("Entity metadata for '{$className}' does not exist.");
		}
		return self::$metadata[$className];
	}


	/**
	 * @param array<string, string> $entityClassesMap
	 * @phpstan-param array<class-string<\Nextras\Orm\Entity\IEntity>, class-string<\Nextras\Orm\Repository\IRepository>> $entityClassesMap
	 */
	public function __construct(
		array $entityClassesMap,
		Cache $cache,
		IMetadataParserFactory $metadataParserFactory,
		IRepositoryLoader $repositoryLoader
	)
	{
		$metadata = $cache->derive('orm.metadata')->load(
			$entityClassesMap,
			function (&$dp) use ($entityClassesMap, $metadataParserFactory, $repositoryLoader): array {
				/** @var EntityMetadata[] $metadata */
				$metadata = [];
				$toProcess = array_keys($entityClassesMap);
				$annotationParser = $metadataParserFactory->create($entityClassesMap);

				while (($className = array_shift($toProcess)) !== null) {
					$metadata[$className] = $annotationParser->parseMetadata($className, $dp[Cache::FILES]);
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
			}
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
	}
}
