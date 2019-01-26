<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\HasMany;
use Nextras\Orm\Relationships\HasOne;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Relationships\ManyHasMany;


class RemovalHelper
{
	public static function getCascadeQueueAndSetNulls(IEntity $entity, IModel $model, bool $withCascade, array & $queuePersist, array & $queueRemove)
	{
		$entityHash = spl_object_hash($entity);
		if (isset($queueRemove[$entityHash])) {
			return;
		}

		$repository = $model->getRepositoryForEntity($entity);
		$repository->attach($entity);
		$repository->onBeforeRemove($entity);

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
	 */
	public static function getRelationships(IEntity $entity): array
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
	 * @param  PropertyMetadata[] $metadata
	 */
	private static function setNulls(IEntity $entity, array $metadata, IModel $model, array & $pre)
	{
		foreach ($metadata as $propertyMeta) {
			assert($propertyMeta->relationship !== null);
			$type = $propertyMeta->relationship->type;
			$name = $propertyMeta->name;

			$value = $entity->hasValue($name) ? $entity->getValue($name) : null;
			if ($value === null || ($value instanceof HasMany && $value->count() === 0)) {
				continue;
			}

			$reverseRepository = $model->getRepository($propertyMeta->relationship->repository);
			$reverseProperty = $propertyMeta->relationship->property
				? $reverseRepository->getEntityMetadata($propertyMeta->relationship->entity)->getProperty($propertyMeta->relationship->property)
				: null;

			if ($type === Relationship::MANY_HAS_MANY) {
				$property = $entity->getProperty($name);
				assert($property instanceof ManyHasMany);
				$pre[] = $property;
				if ($reverseProperty !== null) {
					foreach ($property as $reverseEntity) {
						$pre[] = $reverseEntity->getProperty($reverseProperty->name);
					}
				}
				$entity->setReadOnlyValue($name, []);

			} elseif ($type === Relationship::MANY_HAS_ONE || ($type === Relationship::ONE_HAS_ONE && $propertyMeta->relationship->isMain)) {
				$property = $entity->getProperty($name);
				assert($property instanceof HasOne);
				if ($reverseProperty !== null && $entity->hasValue($name)) {
					$pre[] = $entity->getValue($name)->getProperty($reverseProperty->name);
				}
				$property->set(null, true);

			} else {
				// $type === Relationship::ONE_HAS_MANY or
				// $type === Relationship::ONE_HAS_ONE && !$isMain
				if (!$entity->hasValue($name) || $reverseProperty === null) {
					continue;
				}

				if ($reverseProperty->isNullable) {
					if ($type === Relationship::ONE_HAS_MANY) {
						foreach ($entity->getValue($name) as $subValue) {
							$pre[] = $subValue;
						}
						$entity->setValue($name, []);
					} else {
						$pre[] = $entity->getValue($name);
						$property = $entity->getProperty($name);
						assert($property instanceof HasOne);
						$property->set(null, true);
					}

				} else {
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
