<?php

namespace Nextras\Orm\Repository;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;


class RemovalHelper
{
	public static function getCascadeQueueAndSetNulls(IEntity $entity, IModel $model, $withCascade, & $queuePersist, & $queueRemove)
	{
		$entityHash = spl_object_hash($entity);
		if (isset($queueRemove[$entityHash])) {
			return;
		}

		$repository = $model->getRepositoryForEntity($entity);
		$repository->attach($entity);
		$repository->doFireEvent($entity, 'onBeforeRemove');

		list ($pre, $post, $nulls) = static::getRelationships($entity);
		$prePersist = [];
		static::setNulls($entity, $nulls, $model, $prePersist);

		if (!$withCascade) {
			$queueRemove[$entityHash] = $entity;
			return;
		}

		foreach ($prePersist as $value) {
			$queuePersist[spl_object_hash($value)] = $value;
		}
		foreach ($pre as $value) {
			if ($value instanceof IEntity) {
				static::getCascadeQueueAndSetNulls($value, $model, true, $queuePersist, $queueRemove);
			} elseif ($value instanceof IRelationshipCollection) {
				foreach ($value->getIterator() as $subValue) {
					static::getCascadeQueueAndSetNulls($subValue, $model, true, $queuePersist, $queueRemove);
				}
				$queuePersist[spl_object_hash($value)] = $value;
			}
		}
		$queueRemove[$entityHash] = $entity;
		unset($queuePersist[$entityHash]);
		foreach ($post as $value) {
			if ($value instanceof IEntity) {
				static::getCascadeQueueAndSetNulls($value, $model, true, $queuePersist, $queueRemove);
			} elseif ($value instanceof IRelationshipCollection) {
				foreach ($value->getIterator() as $subValue) {
					static::getCascadeQueueAndSetNulls($subValue, $model, true, $queuePersist, $queueRemove);
				}
				$queuePersist[spl_object_hash($value)] = $value;
			}
		}
	}


	/**
	 * Returns entity relationships as array, 0 => pre, 1 => post, 2 => nulls
	 * @param  IEntity  $entity
	 * @return array
	 */
	public static function getRelationships(IEntity $entity)
	{
		$return = [[], [], []];
		foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
			if ($propertyMeta->relationship === null) {
				continue;
			}

			$name = $propertyMeta->name;
			if (!$propertyMeta->relationship->cascade['remove']) {
				$return[2][$name] = $propertyMeta;
				continue;
			}

			$rawValue = $entity->getRawValue($name);
			if ($rawValue === null && $propertyMeta->isNullable) {
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
	 */
	private static function setNulls($entity, array $metadata, IModel $model, array & $pre)
	{
		foreach ($metadata as $propertyMeta) {
			$type = $propertyMeta->relationship->type;
			$name = $propertyMeta->name;
			if ($type === Relationship::MANY_HAS_MANY) {
				$entity->setValue($name, []);

			} elseif ($type === Relationship::MANY_HAS_ONE || ($type === Relationship::ONE_HAS_ONE && $propertyMeta->relationship->isMain)) {
				$entity->getProperty($name)->set(null, true);

			} else {
				// $type === Relationship::ONE_HAS_MANY or
				// $type === Relationship::ONE_HAS_ONE && !$isMain
				if (!$entity->hasValue($name)) {
					continue;
				}

				$reverseRepository = $model->getRepository($propertyMeta->relationship->repository);
				$reverseProperty = $reverseRepository->getEntityMetadata()->getProperty($propertyMeta->relationship->property);

				if ($reverseProperty->isNullable) {
					if ($type === Relationship::ONE_HAS_MANY) {
						foreach ($entity->getValue($name) as $subValue) {
							$pre[] = $subValue;
						}
						$entity->getValue($name)->set([]);
					} else {
						$pre[] = $entity->getValue($name);
						$entity->getProperty($name)->set(null, true);
					}

				} else {
					if ($type === Relationship::ONE_HAS_MANY && $entity->getValue($name)->count() === 0) {
						continue;
					}

					$entityClass = get_class($entity);
					$reverseEntityClass = $propertyMeta->relationship->entity;
					$primaryValue = $entity->getValue('id');
					$primaryValue = is_array($primaryValue) ? '[' . implode(', ', $primaryValue) . ']' : $primaryValue;
					throw new InvalidStateException(
						"Cannot remove {$entityClass}::\$id={$primaryValue} because {$reverseEntityClass}::\${$reverseProperty->name} cannot be a null."
					);
				}
			}
		}
	}
}
