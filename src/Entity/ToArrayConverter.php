<?php declare(strict_types = 1);

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
	/**
	 * @const
	 * IRelationshipContainer property is returned as IEntity entity.
	 * IRelationshipCollection property is returned as array of its IEntity entities.
	 * Other properties are not changed.
	 */
	const RELATIONSHIP_AS_IS = 1;

	/**
	 * @const
	 * IRelationshipContainer property is returned as entity id.
	 * IRelationshipCollection property is returned as array of entity ids.
	 * Other properties are not changed.
	 */
	const RELATIONSHIP_AS_ID = 2;

	/**
	 * @const
	 * IRelationshipContainer property is returned as array (entity tranformed to array).
	 * IRelationshipCollection property is returned as array of array (entities tranformed to array).
	 * Other properties are not changed.
	 */
	const RELATIONSHIP_AS_ARRAY = 3;


	/** @var int Maximum recursion level. */
	public static $maxRecursionLevel = 3;


	/**
	 * Converts IEntity to an array.
	 */
	public static function toArray(IEntity $entity, int $type = self::RELATIONSHIP_AS_IS, int $recursionLevel = 0): array
	{
		$return = [];
		$metadata = $entity->getMetadata();

		foreach ($metadata->getProperties() as $name => $metadataProperty) {
			if (!$entity->hasValue($name)) {
				$value = null;
			} else {
				$value = $entity->getValue($name);
			}

			if ($value instanceof IEntity) {
				if ($type === self::RELATIONSHIP_AS_ID) {
					$value = $value->getValue('id');
				} elseif ($type === self::RELATIONSHIP_AS_ARRAY) {
					if ($recursionLevel + 1 >= static::$maxRecursionLevel) {
						$value = null;
					} else {
						$value = static::toArray($value, $type, $recursionLevel + 1);
					}
				}

			} elseif ($value instanceof IRelationshipCollection) {
				if ($type === self::RELATIONSHIP_AS_ID) {
					$collection = [];
					foreach ($value as $collectionEntity) {
						$collection[] = $collectionEntity->getValue('id');
					}
					$value = $collection;

				} elseif ($type === self::RELATIONSHIP_AS_ARRAY) {
					$collection = [];
					if ($recursionLevel + 1 < static::$maxRecursionLevel) {
						foreach ($value as $collectionEntity) {
							$collection[] = static::toArray($collectionEntity, $type, $recursionLevel + 1);
						}
					}
					$value = $collection;
				}
			}

			$return[$name] = $value;
		}

		return $return;
	}
}
