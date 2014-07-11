<?php

/**
 * This file is part of the Nextras\ORM library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;


class ToArrayConverter
{
	/** @var int Maximum recursion level. */
	public static $maxRecursionLevel = 3;


	/**
	 * Converts IEntity to array
	 * @param  IEntity  $entity
	 * @param  int      $type
	 * @param  int      $recursionLevel
	 * @return array|null
	 */
	public static function toArray(IEntity $entity, $type = IEntity::TO_ARRAY_RELATIONSHIP_AS_IS, $recursionLevel = 0)
	{
		if ($recursionLevel >= static::$maxRecursionLevel) {
			return NULL;
		}

		$return = [];
		$metadata = $entity->getMetadata();

		foreach ($metadata->storageProperties as $name) {
			if ($name === 'id' && !$entity->hasValue('id')) {
				$value = NULL;
			} elseif ($type !== IEntity::TO_ARRAY_LOADED_RELATIONSHIP_AS_IS) {
				$value = $entity->getValue($name);
			} else {
				$property = $entity->getProperty($name);
				if ($property instanceof IRelationshipContainer) {
					$value = $property->getPrimaryValue();
				} elseif ($property instanceof IRelationshipCollection) {
					if (!$property->isLoaded()) {
						continue;
					} else {
						$value = $entity->getValue($name);
					}
				} else {
					$value = $entity->getValue($name);
				}
			}

			if ($value instanceof IEntity) {
				if ($type === IEntity::TO_ARRAY_RELATIONSHIP_AS_ID) {
					$value = $value->id;
				} elseif ($type === IEntity::TO_ARRAY_RELATIONSHIP_AS_ARRAY) {
					$value = static::toArray($value, $type, $recursionLevel + 1);
				}

			} elseif ($value instanceof IRelationshipCollection) {
				if ($type === IEntity::TO_ARRAY_RELATIONSHIP_AS_ID) {
					$collection = [];
					foreach ($value as $collectionEntity) {
						$collection[] = $collectionEntity->id;
					}
					$value = $collection;

				} elseif ($type === IEntity::TO_ARRAY_RELATIONSHIP_AS_ARRAY) {
					$collection = [];
					foreach ($value as $collectionEntity) {
						$collection[] = static::toArray($collectionEntity, $type, $recursionLevel + 1);
					}
					$value = $collection;
				}

			}

			$return[$name] = $value;
		}

		return $return;
	}

}
