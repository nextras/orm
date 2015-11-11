<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;


class PersistanceHelper
{
	public static function getCascadeQueue($entity, IModel $model, $withCascade, & $queue = [])
	{
		$entityHash = spl_object_hash($entity);
		if (isset($queue[$entityHash])) {
			return;
		}

		$repository = $model->getRepositoryForEntity($entity);
		$repository->attach($entity);
		$repository->doFireEvent($entity, 'onBeforePersist');

		if (!$withCascade) {
			$queue[$entityHash] = $entity;
			return;
		}

		list ($pre, $post) = static::getLoadedRelationships($entity);
		foreach ($pre as $value) {
			if ($value instanceof IEntity) {
				static::getCascadeQueue($value, $model, TRUE, $queue);
			} elseif ($value instanceof IRelationshipCollection) {
				foreach ($value->getEntitiesForPersistance() as $subValue) {
					static::getCascadeQueue($subValue, $model, TRUE, $queue);
				}
				$queue[spl_object_hash($value)] = $value;
			}
		}
		$queue[$entityHash] = $entity;
		foreach ($post as $value) {
			if ($value instanceof IEntity) {
				static::getCascadeQueue($value, $model, TRUE, $queue);
			} elseif ($value instanceof IRelationshipCollection) {
				foreach ($value->getEntitiesForPersistance() as $subValue) {
					static::getCascadeQueue($subValue, $model, TRUE, $queue);
				}
				$queue[spl_object_hash($value)] = $value;
			}
		}
	}


	/**
	 * Returns entity relationships as array, 0 => pre, 1 => post
	 * @param  IEntity  $entity
	 * @return array
	 */
	public static function getLoadedRelationships(IEntity $entity)
	{
		$isPersisted = $entity->isPersisted();
		$return = [[], []];
		foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
			if ($propertyMeta->relationship === NULL || !$propertyMeta->relationship->cascade['persist']) {
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
					if ($propertyMeta->relationship->type === Relationship::ONE_HAS_ONE && !$propertyMeta->relationship->isMain) {
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
