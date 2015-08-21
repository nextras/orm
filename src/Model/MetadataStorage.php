<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Model;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Object;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\MetadataValidator;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;


class MetadataStorage extends Object
{
	/** @var EntityMetadata[] */
	private static $metadata;


	public static function get($className)
	{
		if (!isset(static::$metadata[$className])) {
			if (static::$metadata === NULL) {
				throw new InvalidStateException("MetadataStorage::get() called too early. You have to instantiate your model first.");
			}
			throw new InvalidArgumentException("Entity metadata for '{$className}' does not exist.");
		}
		return static::$metadata[$className];
	}


	public function __construct(IStorage $cacheStorage, array $entityClassesMap, IRepositoryLoader $repositoryLoader)
	{
		$cache = new Cache($cacheStorage, 'Nextras.Orm.metadata');
		static::$metadata = $cache->load($entityClassesMap, function(& $dp) use ($entityClassesMap, $repositoryLoader) {

			$metadata = [];
			$annotationParser = new AnnotationParser($entityClassesMap);
			foreach (array_keys($entityClassesMap) as $className) {
				$metadata[$className] = $annotationParser->parseMetadata($className, $dp[Cache::FILES]);
			}

			$validator = new MetadataValidator();
			$validator->validate($metadata, $repositoryLoader);

			return $metadata;

		});
	}

}
