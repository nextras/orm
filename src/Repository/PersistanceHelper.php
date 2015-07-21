<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;


class PersistanceHelper
{

	/**
	 * Returns entity relationships as array, 0 => prePersist, 1 => postPersist
	 * @param  IEntity  $entity
	 * @return array
	 */
	public static function getLoadedRelationships(IEntity $entity)
	{
		$isPersisted = $entity->isPersisted();
		$return = [[], []];
		foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
			if ($propertyMeta->relationship === NULL) {
				continue;
			}
			$name = $propertyMeta->name;
			$rawValue = $entity->getRawProperty($name);
			if (!is_object($rawValue) && ($propertyMeta->isNullable || $isPersisted)) {
				continue;
			}

			$property = $entity->getProperty($name);
			if ($property instanceof IRelationshipContainer) {
				if (!$property->isLoaded() && $isPersisted) {
					continue;
				}

				$value = $entity->getValue($name);
				if ($value) {
					if ($propertyMeta->relationship->type === PropertyRelationshipMetadata::ONE_HAS_ONE_DIRECTED && !$propertyMeta->relationship->isMain) {
						$return[1][$name] = $value;
					} else {
						$return[0][$name] = $value;
					}
				}

			} elseif ($property instanceof IRelationshipCollection) {
				if (!$property->isLoaded() && $isPersisted) {
					continue;
				}

				$value = $entity->getValue($name);
				if ($value) {
					$return[1][$name] = $value;
				}
			}
		}

		return $return;
	}

}
