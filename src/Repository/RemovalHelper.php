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
	 * @param array<string, IEntity|IRelationshipCollection> $queuePersist
	 * @param array<string, IEntity|bool> $queueRemove
	 */
	public static function getCascadeQueueAndSetNulls(
		IEntity $entity,
		IModel $model,
		bool $withCascade,
		array &$queuePersist,
		array &$queueRemove
	): void
	{
		$entityHash = spl_object_hash($entity);
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
			$queuePersist[spl_object_hash($value)] = $value;
		}
		$queueRemove[$entityHash] = true;
		foreach ($pre as $value) {
			if ($value instanceof IEntity) {
				static::getCascadeQueueAndSetNulls($value, $model, true, $queuePersist, $queueRemove);
			} else {
				foreach ($value->getIterator() as $subValue) {
					static::getCascadeQueueAndSetNulls($subValue, $model, true, $queuePersist, $queueRemove);
				}
				$queuePersist[spl_object_hash($value)] = $value;
			}
		}
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
				$queuePersist[spl_object_hash($value)] = $value;
			}
		}
	}


	/**
	 * Returns entity relationships as array, 0 => pre, 1 => post, 2 => nulls
	 * @phpstan-return array{
	 *      array<string, IEntity|IRelationshipCollection>,
	 *      array<string, IEntity|IRelationshipCollection>,
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
	 * @param array<string, IEntity|IRelationshipCollection> $pre
	 * @param array<string, IEntity|bool> $queueRemove
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

			$value = $entity->hasValue($name) ? $entity->getValue($name) : null;
			if ($value === null || ($value instanceof HasMany && $value->count() === 0)) {
				continue;
			}

			$reverseRepository = $model->getRepository($propertyMeta->relationship->repository);
			$reverseProperty = $propertyMeta->relationship->property !== null
				? $reverseRepository->getEntityMetadata($propertyMeta->relationship->entity)
					->getProperty($propertyMeta->relationship->property)
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
				$property->set([]);
			} elseif ($type === Relationship::MANY_HAS_ONE || ($type === Relationship::ONE_HAS_ONE && $propertyMeta->relationship->isMain)) {
				$property = $entity->getProperty($name);
				assert($property instanceof HasOne);
				if ($reverseProperty !== null) {
					$reverseEntity = $property->getEntity();
					if ($reverseEntity === null || isset($queueRemove[spl_object_hash($reverseEntity)])) {
						// reverse side is also being removed, do not set null to this relationship
						continue;
					}
					$pre[] = $reverseEntity->getProperty($reverseProperty->name);
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
						$property = $entity->getProperty($name);
						assert($property instanceof IRelationshipCollection);
						foreach ($property as $subValue) {
							$pre[] = $subValue;
						}
						$property->set([]);
					} else {
						$property = $entity->getProperty($name);
						assert($property instanceof HasOne);
						$pre[] = $property->getEntity();
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
