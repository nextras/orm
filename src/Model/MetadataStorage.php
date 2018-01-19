<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Model;

use Nette\Caching\Cache;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataValidator;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;


class MetadataStorage
{
	/** @var EntityMetadata[] */
	private static $metadata = [];


	public static function get(string $className): EntityMetadata
	{
		if (!isset(static::$metadata[$className])) {
			if (static::$metadata === []) {
				throw new InvalidStateException("MetadataStorage::get() called too early. You have to instantiate your model first.");
			}
			throw new InvalidArgumentException("Entity metadata for '{$className}' does not exist.");
		}
		return static::$metadata[$className];
	}


	public function __construct(
		array $entityClassesMap,
		Cache $cache,
		IMetadataParserFactory $metadataParserFactory,
		IRepositoryLoader $repositoryLoader
	)
	{
		$metadata = $cache->derive('metadata')->load(
			$entityClassesMap,
			function (& $dp) use ($entityClassesMap, $metadataParserFactory, $repositoryLoader) {
				$metadata = [];
				$annotationParser = $metadataParserFactory->create($entityClassesMap);
				foreach (array_keys($entityClassesMap) as $className) {
					$metadata[$className] = $annotationParser->parseMetadata($className, $dp[Cache::FILES]);
				}

				$validator = new MetadataValidator();
				$validator->validate($metadata, $repositoryLoader);

				return $metadata;
			}
		);
		/** @var EntityMetadata $entityMetadata */
		foreach ($metadata as $entityMetadata) {
			foreach ($entityMetadata->getProperties() as $property) {
				if ($property->relationship) {
					$property->relationship->entityMetadata = $metadata[$property->relationship->entity];
				}
			}
		}

		static::$metadata += $metadata;
	}
}
