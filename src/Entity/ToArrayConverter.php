<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Relationships\IRelationshipCollection;


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
			return null;
		}

		$return = [];
		$metadata = $entity->getMetadata();

		foreach ($metadata->getProperties() as $name => $metadataProperty) {
			if (!$entity->hasValue($name)) {
				$value = null;
			} else {
				$value = $entity->getValue($name);
			}

			if ($value instanceof IEntity) {
				if ($type === IEntity::TO_ARRAY_RELATIONSHIP_AS_ID) {
					$value = $value->getValue('id');
				} elseif ($type === IEntity::TO_ARRAY_RELATIONSHIP_AS_ARRAY) {
					$value = static::toArray($value, $type, $recursionLevel + 1);
				}

			} elseif ($value instanceof IRelationshipCollection) {
				if ($type === IEntity::TO_ARRAY_RELATIONSHIP_AS_ID) {
					$collection = [];
					foreach ($value as $collectionEntity) {
						$collection[] = $collectionEntity->getValue('id');
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
