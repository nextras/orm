<?php declare(strict_types = 1);

namespace Nextras\Orm\Repository;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\HasMany;
use Nextras\Orm\Relationships\HasOne;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Relationships\ManyHasMany;
use function assert;


class RemovalHelper
{
	/**
	 * @param array<int, IEntity|IRelationshipContainer<IEntity>|IRelationshipCollection<IEntity>> $queuePersist
	 * @param array<int, IEntity> $queueRemove
	 */
	public static function getCascadeQueueAndSetNulls(
		IEntity $entity,
		IModel $model,
		bool $withCascade,
		array &$queuePersist,
		array &$queueRemove
	): void
	{
		$entityHash = spl_object_id($entity);
		if (isset($queueRemove[$entityHash])) {
			return;
		}

		$repository = $model->getRepositoryForEntity($entity);
		$repository->attach($entity);
		$repository->onBeforeRemove($entity);

		[$pre, $post, $nulls] = static::getRelationships($entity);
		$prePersist = [];
		self::setNulls($entity, $nulls, $model, $prePersist, $queueRemove);

		if (!$withCascade) {
			$queueRemove[$entityHash] = $entity;
			return;
		}

		foreach ($prePersist as $value) {
			$queuePersist[spl_object_id($value)] = $value;
		}
		$queueRemove[$entityHash] = $entity;
		foreach ($pre as $value) {
			if ($value instanceof IEntity) {
				static::getCascadeQueueAndSetNulls($value, $model, true, $queuePersist, $queueRemove);
			} else {
				foreach ($value->getIterator() as $subValue) {
					static::getCascadeQueueAndSetNulls($subValue, $model, true, $queuePersist, $queueRemove);
				}
				$queuePersist[spl_object_id($value)] = $value;
			}
		}
		// re-enqueue to be at the last position
		unset($queueRemove[$entityHash]);
		$queueRemove[$entityHash] = $entity;
		unset($queuePersist[$entityHash]);
		foreach ($post as $value) {
			if ($value instanceof IEntity) {
				static::getCascadeQueueAndSetNulls($value, $model, true, $queuePersist, $queueRemove);
			} else {
				foreach ($value->getIterator() as $subValue) {
					static::getCascadeQueueAndSetNulls($subValue, $model, true, $queuePersist, $queueRemove);
				}
				$queuePersist[spl_object_id($value)] = $value;
			}
		}
	}


	/**
	 * Returns entity relationships as array, 0 => pre, 1 => post, 2 => nulls
	 * @return array{
	 *      array<string, IEntity|IRelationshipCollection<IEntity>>,
	 *      array<string, IEntity|IRelationshipCollection<IEntity>>,
	 *      array<string, PropertyMetadata>
	 * }
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
				$value = $property->getEntity();
				if ($value !== null) {
					if ($propertyMeta->relationship->type === Relationship::ONE_HAS_ONE && !$propertyMeta->relationship->isMain) {
						$return[0][$name] = $value;
					} else {
						$return[1][$name] = $value;
					}
				}
			} elseif ($property instanceof IRelationshipCollection) {
				$return[0][$name] = $property;
			}
		}

		return $return;
	}


	/**
	 * @param PropertyMetadata[] $metadata
	 * @param list<IEntity|IRelationshipContainer<IEntity>|IRelationshipCollection<IEntity>> $pre
	 * @param array<int, IEntity|bool> $queueRemove
	 */
	private static function setNulls(
		IEntity $entity,
		array $metadata,
		IModel $model,
		array &$pre,
		array $queueRemove
	): void
	{
		foreach ($metadata as $propertyMeta) {
			assert($propertyMeta->relationship !== null);
			$type = $propertyMeta->relationship->type;
			$name = $propertyMeta->name;

			if (!$entity->hasValue($name)) continue;

			$value = $entity->getValue($name);
			if ($value instanceof HasMany && $value->count() === 0) continue;

			$reverseRepository = $model->getRepository($propertyMeta->relationship->repository);
			$reverseProperty = $propertyMeta->relationship->property !== null
				? $reverseRepository->getEntityMetadata($propertyMeta->relationship->entity)
					->getProperty($propertyMeta->relationship->property)
				: null;

			if ($type === Relationship::MANY_HAS_MANY) {
				/** @var ManyHasMany<IEntity> $property */
				$property = $entity->getProperty($name);
				assert($property instanceof ManyHasMany);
				$pre[] = $property;
				if ($reverseProperty !== null) {
					foreach ($property as $reverseEntity) {
						/** @var ManyHasMany<IEntity> $reverseRelationship */
						$reverseRelationship = $reverseEntity->getProperty($reverseProperty->name);
						$pre[] = $reverseRelationship;
					}
				}
				$property->set([]);
			} elseif ($type === Relationship::MANY_HAS_ONE || $type === Relationship::ONE_HAS_ONE) {
				$property = $entity->getProperty($name);
				assert($property instanceof HasOne);
				if ($reverseProperty !== null) {
					$reverseEntity = $property->getEntity();
					if ($reverseEntity === null || isset($queueRemove[spl_object_id($reverseEntity)])) {
						// The reverse side is also being removed, do not set null to this relationship.
						continue;
					}
					/** @var HasOne<IEntity> $reverseRelationship */
					$reverseRelationship = $reverseEntity->getProperty($reverseProperty->name);
					$pre[] = $reverseRelationship;
					$pre[] = $reverseEntity;
				}
				$property->set(null, true);
			} else {
				// $type === Relationship::ONE_HAS_MANY
				if ($reverseProperty === null) continue;

				if ($reverseProperty->isNullable) {
					$property = $entity->getProperty($name);
					assert($property instanceof IRelationshipCollection);
					foreach ($property as $subValue) {
						assert($subValue instanceof IEntity);
						$pre[] = $subValue;
					}
					$property->set([]);
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
