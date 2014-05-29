<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Model;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Object;
use Nextras\Orm\Entity\Reflection\AnnotationParser;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\MetadataValidator;
use Nextras\Orm\InvalidArgumentException;


class MetadataStorage extends Object
{
	/** @var EntityMetadata[] */
	private static $metadata;


	public static function get($className)
	{
		if (!isset(static::$metadata[$className])) {
			throw new InvalidArgumentException("Entity metadata for '{$className}' does not exist.");
		}
		return static::$metadata[$className];
	}


	public function __construct(IStorage $cacheStorage, array $entityClasses, IModel $model)
	{
		$cache = new Cache($cacheStorage, 'Nextras.Orm.metadata');
		static::$metadata = $cache->load($entityClasses, function(& $dp) use ($entityClasses, $model) {
			$metadata = $this->parseMetadata($entityClasses, $dp[Cache::FILES]);
			(new MetadataValidator())->validate($metadata, $model);
			return $metadata;
		});
	}


	private function parseMetadata($entityList, & $fileDependencies)
	{
		$cache = [];
		foreach ($entityList as $className) {
			$annotationParser = new AnnotationParser($className);
			$cache[$className] = $annotationParser->getMetadata($fileDependencies);
		}

		return $cache;
	}

}
