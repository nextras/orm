<?php

namespace Nextras\Orm\Repository;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\IRelationshipCollection;


class RemovalHelper
{
	public static function getCascadeQueueAndSetNulls(IEntity $entity, IModel $model, $withCascade, & $queue = [])
	{
		$entityHash = spl_object_hash($entity);
		if (isset($queue[$entityHash])) {
			return;
		}

		$repository = $model->getRepositoryForEntity($entity);
		$repository->attach($entity);
		$repository->doFireEvent($entity, 'onBeforeRemove');

		list ($pre, $post, $nulls) = static::getLoadedRelationships($entity);
		static::setNulls($entity, $nulls, $model, $pre, $post);

		if (!$withCascade) {
			$queue[$entityHash] = $entity;
			return;
		}

		foreach ($pre as $value) {
			if ($value instanceof IEntity) {
				static::getCascadeQueueAndSetNulls($value, $model, TRUE, $queue);
			} elseif ($value instanceof IRelationshipCollection) {
				foreach ($value->getIterator() as $subValue) {
					static::getCascadeQueueAndSetNulls($subValue, $model, TRUE, $queue);
				}
				$queue[spl_object_hash($value)] = $value;
			}
		}
		$queue[$entityHash] = $entity;
		foreach ($post as $value) {
			if ($value instanceof IEntity) {
				static::getCascadeQueueAndSetNulls($value, $model, TRUE, $queue);
			} elseif ($value instanceof IRelationshipCollection) {
				foreach ($value->getIterator() as $subValue) {
					static::getCascadeQueueAndSetNulls($subValue, $model, TRUE, $queue);
				}
				$queue[spl_object_hash($value)] = $value;
			}
		}
	}


	/**
	 * Returns entity relationships as array, 0 => pre, 1 => post, 2 => nulls
	 * @param  IEntity  $entity
	 * @return array
	 */
	public static function getLoadedRelationships(IEntity $entity)
	{
		$return = [[], [], []];
		foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
			if ($propertyMeta->relationship === NULL) {
				continue;
			}

			$name = $propertyMeta->name;
			if (!$propertyMeta->relationship->cascade['remove']) {
				$return[2][$name] = $propertyMeta;
				continue;
			}

			$rawValue = $entity->getRawProperty($name);
			if (!is_object($rawValue) && $propertyMeta->isNullable) {
				continue;
			}

			$property = $entity->getProperty($name);
			if ($property instanceof IRelationshipContainer) {
				$value = $entity->getValue($name);
				if ($value) {
					if ($propertyMeta->relationship->type === Relationship::ONE_HAS_ONE && !$propertyMeta->relationship->isMain) {
						$return[0][$name] = $value;
					} else {
						$return[1][$name] = $value;
					}
				}

			} elseif ($property instanceof IRelationshipCollection) {
				$return[0][$name] = $entity->getValue($name);
			}
		}

		return $return;
	}


	/**
	 * @param  IEntity $entity
	 * @param  PropertyMetadata[] $metadata
	 * @parma  IModel $model
	 * @param  array $pre
	 * @param  array $post
	 */
	private static function setNulls($entity, array $metadata, IModel $model, array & $pre, array & $post)
	{
		foreach ($metadata as $propertyMeta) {
			$type = $propertyMeta->relationship->type;
			if ($type === Relationship::MANY_HAS_MANY) {
				$entity->setValue($propertyMeta->name, []);

			} else {
				$reverseRepository = $model->getRepository($propertyMeta->relationship->repository);
				$reverseProperty = $reverseRepository->getEntityMetadata()->getProperty($propertyMeta->relationship->property);

				if ($reverseProperty->isNullable || Relationship::ONE_HAS_MANY) {
					$default = $type === Relationship::ONE_HAS_MANY ? [] : NULL;
					$entity->getProperty($propertyMeta->name)->set($default, TRUE);

				} else {
					throw new InvalidStateException($propertyMeta->name);
				}
			}
		}
	}
}
