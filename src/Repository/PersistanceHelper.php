<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\IModel;


class PersistanceHelper
{
	/**
	 * @param  IEntity $entity
	 * @param  IModel $model
	 * @param  bool $withCascade
	 * @param  array $queue
	 * @return void
	 */
	public static function getCascadeQueue(IEntity $entity, IModel $model, $withCascade, array & $queue)
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
		$queue[$entityHash] = TRUE;

		$keys = [[], []];
		foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
			if ($propertyMeta->relationship === NULL || !$propertyMeta->relationship->cascade['persist']) {
				continue;
			}
			$relType = $propertyMeta->relationship->type;
			$relIsMain = $propertyMeta->relationship->isMain;
			$storesRel = ($relType === Relationship::ONE_HAS_ONE && $relIsMain === TRUE) || $relType === Relationship::MANY_HAS_ONE;
			$keys[$storesRel ? 0 : 1][] = $propertyMeta;
		}

		foreach ($keys[0] as $propertyMeta) {
			self::addRelationtionToQueue($entity, $propertyMeta, $model, TRUE, $queue);
		}

		unset($queue[$entityHash]); // reenqueue
		$queue[$entityHash] = $entity;

		foreach ($keys[1] as $propertyMeta) {
			self::addRelationtionToQueue($entity, $propertyMeta, $model, FALSE, $queue);
		}
	}


	protected static function addRelationtionToQueue(IEntity $entity, PropertyMetadata $propertyMeta, IModel $model, $checkCycles, array & $queue)
	{
		$isPersisted = $entity->isPersisted();
		$rawValue = $entity->getRawProperty($propertyMeta->name);
		if ($rawValue === NULL && ($propertyMeta->isNullable || $isPersisted)) {
			return;
		} elseif (!$entity->getProperty($propertyMeta->name)->isLoaded() && $isPersisted) {
			return;
		}

		$relType = $propertyMeta->relationship->type;
		$value = $entity->getValue($propertyMeta->name);
		if ($relType === Relationship::ONE_HAS_ONE || $relType === Relationship::MANY_HAS_ONE) {
			if ($value !== NULL) {
				if ($checkCycles && isset($queue[spl_object_hash($value)]) && $queue[spl_object_hash($value)] === TRUE  && !$value->isPersisted()) {
					$entityClass = get_class($entity);
					throw new InvalidStateException(
						"Persist cycle detected in $entityClass::\${$propertyMeta->name}. Use manual two phase persist."
					);
				}
				self::getCascadeQueue($value, $model, TRUE, $queue);
			}
		} else {
			foreach ($value->getEntitiesForPersistance() as $subValue) {
				self::getCascadeQueue($subValue, $model, TRUE, $queue);
			}
			$queue[spl_object_hash($value)] = $value;
		}
	}
}
