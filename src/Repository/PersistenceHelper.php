<?php declare(strict_types = 1);

namespace Nextras\Orm\Repository;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\HasMany;
use Nextras\Orm\Relationships\HasOne;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Relationships\ManyHasMany;
use function array_filter;
use function assert;


class PersistenceHelper
{
	/** @var array<int, IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity>> */
	protected static array $inputQueue = [];

	/** @var array<int, IEntity|IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity>|true> */
	protected static array $outputPersistQueue = [];

	/** @var array<int, IEntity|true> */
	protected static array $outputRemoveQueue = [];


	/**
	 * @see https://en.wikipedia.org/wiki/Topological_sorting#Depth-first_search
	 * @return array{
	 *     array<int, IEntity|IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity>>,
	 *     array<int, IEntity>,
	 * }
	 */
	public static function getCascadeQueue(
		IEntity $entity,
		PersistenceMode $mode,
		IModel $model,
		bool $withCascade,
	): array
	{
		try {
			self::visitEntity($entity, $mode, $model, $withCascade);

			while (count(self::$inputQueue) > 0) {
				$value = array_shift(self::$inputQueue);
				self::visitRelationship($value, $mode, $model);
			}

			return [
				array_filter(self::$outputPersistQueue, fn ($val) => assert($val !== true)),
				array_filter(self::$outputRemoveQueue, fn ($val) => assert($val !== true)),
			];
		} finally {
			self::$inputQueue = [];
			self::$outputPersistQueue = [];
			self::$outputRemoveQueue = [];
		}
	}


	protected static function visitEntity(
		IEntity $entity,
		PersistenceMode $mode,
		IModel $model,
		bool $withCascade = true,
	): void
	{
		$entityId = spl_object_id($entity);
		$checkedQueue = match ($mode) {
			PersistenceMode::Persist => self::$outputPersistQueue,
			PersistenceMode::Remove => self::$outputRemoveQueue,
		};
		if (isset($checkedQueue[$entityId])) {
			if ($checkedQueue[$entityId] === true) {
				$cycle = [];
				$bt = debug_backtrace();
				foreach ($bt as $item) {
					if ($item['function'] === 'getCascadeQueue') {
						break;
					} elseif ($item['function'] === 'enqueueRelationship' && isset($item['args'])) {
						$cycle[] = get_class($item['args'][0]) . '::$' . $item['args'][2]->name;
					}
				}
				$cycle = array_reverse($cycle);
				throw new InvalidStateException('Persist cycle detected in ' . implode(' - ', $cycle) . '. Use manual two-phase persist.');
			}
			return;
		}

		$repository = $model->getRepositoryForEntity($entity);
		$repository->attach($entity);
		if ($mode === PersistenceMode::Persist) {
			$repository->onBeforePersist($entity);
		} else {
			$repository->onBeforeRemove($entity);
		}

		if ($mode === PersistenceMode::Remove) {
			self::$outputRemoveQueue[$entityId] = true;
			foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
				if ($propertyMeta->relationship !== null) {
					self::enqueueRelationship(
						entity: $entity,
						mode: $mode,
						propertyMeta: $propertyMeta,
						relationshipMeta: $propertyMeta->relationship,
						model: $model,
						nullOnly: !$withCascade,
					);
				}
			}
			unset(self::$outputPersistQueue[$entityId]);
			unset(self::$outputRemoveQueue[$entityId]); // re-enqueue
			self::$outputRemoveQueue[$entityId] = $entity;
		} elseif ($withCascade) {
			self::$outputPersistQueue[$entityId] = true;
			foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
				if ($propertyMeta->relationship !== null) {
					self::enqueueRelationship(
						entity: $entity,
						mode: $mode,
						propertyMeta: $propertyMeta,
						relationshipMeta: $propertyMeta->relationship,
						model: $model,
					);
				}
			}
			unset(self::$outputPersistQueue[$entityId]); // re-enqueue
			self::$outputPersistQueue[$entityId] = $entity;
		} else {
			self::$outputPersistQueue[$entityId] = $entity;
		}
	}


	/**
	 * @param IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity> $rel
	 */
	protected static function visitRelationship(
		IRelationshipContainer|IRelationshipCollection $rel,
		PersistenceMode $mode,
		IModel $model,
	): void
	{
		foreach ($rel->getEntitiesForPersistence() as $entity) {
			self::visitEntity($entity, $mode, $model);
		}

		self::$outputPersistQueue[spl_object_id($rel)] = $rel;
	}


	protected static function enqueueRelationship(
		IEntity $entity,
		PersistenceMode $mode,
		PropertyMetadata $propertyMeta,
		PropertyRelationshipMetadata $relationshipMeta,
		IModel $model,
		bool $nullOnly = false,
	): void
	{
		if ($mode === PersistenceMode::Persist) {
			if ($relationshipMeta->cascade['persist'] !== true) {
				return;
			}

			$isPersisted = $entity->isPersisted();
			$relationship = $entity->getRawProperty($propertyMeta->name);
			if ($relationship === null || (!($relationship instanceof IRelationshipCollection || $relationship instanceof IRelationshipContainer) && $isPersisted)) {
				// 1. relationship is not initialized at all
				// 2. relationship has a scalar value and the entity is persisted -> no change
				return;
			}

			assert($relationship instanceof IRelationshipCollection || $relationship instanceof IRelationshipContainer);
			if (!$relationship->isLoaded() && $isPersisted) {
				return;
			}

			if ($relationship instanceof IRelationshipContainer) {
				$immediateEntity = $relationship->getImmediateEntityForPersistence();
				if ($immediateEntity !== null) {
					self::visitEntity($immediateEntity, $mode, $model);
				}
			}

			self::$inputQueue[] = $relationship;
		} else {
			if ($relationshipMeta->cascade['remove'] !== true) {
				self::nullRelationship($entity, $propertyMeta, $relationshipMeta, $model);
				return;
			}

			if ($nullOnly) {
				return;
			}

			$rawValue = $entity->getRawValue($propertyMeta->name);
			if ($rawValue === null && $propertyMeta->isNullable) {
				return;
			}

			$relationship = $entity->getProperty($propertyMeta->name);
			if ($relationship instanceof IRelationshipContainer) {
				$canSkip = !$relationship->isLoaded() && !$relationshipMeta->isMain;
				if ($canSkip) {
					return;
				}
				$value = $relationship->getEntity();
				if ($value !== null) {
					if ($relationshipMeta->type === Relationship::ONE_HAS_ONE && !$relationshipMeta->isMain) {
						self::visitEntity($value, $mode, $model);
					} else {
						self::$inputQueue[] = $relationship;
					}
				}
			} elseif ($relationship instanceof IRelationshipCollection) {
				foreach ($relationship->getIterator() as $subValue) {
					self::visitEntity($subValue, $mode, $model);
				}
				self::$outputPersistQueue[spl_object_id($relationship)] = $relationship;
			}
		}
	}


	protected static function nullRelationship(
		IEntity $entity,
		PropertyMetadata $propertyMeta,
		PropertyRelationshipMetadata $relationshipMeta,
		IModel $model,
	): void
	{
		$type = $relationshipMeta->type;
		$name = $propertyMeta->name;

		$reverseRepository = $model->getRepository($relationshipMeta->repository);
		$reversePropertyMeta = $relationshipMeta->property !== null
			? $reverseRepository->getEntityMetadata($relationshipMeta->entity)
				->getProperty($relationshipMeta->property)
			: null;

		if ($type === Relationship::MANY_HAS_MANY) {
			/** @var ManyHasMany<IEntity> $property */
			$property = $entity->getProperty($name);
			assert($property instanceof ManyHasMany);
			self::$outputPersistQueue[spl_object_id($property)] = $property;
			if ($reversePropertyMeta !== null) {
				foreach ($property as $reverseEntity) {
					/** @var ManyHasMany<IEntity> $reverseRelationship */
					$reverseRelationship = $reverseEntity->getProperty($reversePropertyMeta->name);
					self::$outputPersistQueue[spl_object_id($reverseRelationship)] = $reverseRelationship;
				}
			}
			$property->set([]);
		} elseif ($type === Relationship::MANY_HAS_ONE || $type === Relationship::ONE_HAS_ONE) {
			$property = $entity->getProperty($name);
			assert($property instanceof HasOne);
			$canSkip = (!$property->isLoaded() && !$relationshipMeta->isMain) || $property->getRawValue() === null;
			if ($canSkip) {
				return;
			}
			if ($reversePropertyMeta !== null) {
				$reverseEntity = $property->getEntity();
				if ($reverseEntity === null || isset(self::$outputRemoveQueue[spl_object_id($reverseEntity)])) {
					// The reverse side is also being removed, do not set null to this relationship.
					return;
				}
				/** @var HasOne<IEntity> $reverseRelationship */
				$reverseRelationship = $reverseEntity->getProperty($reversePropertyMeta->name);
				self::$outputPersistQueue[spl_object_id($reverseRelationship)] = $reverseRelationship;
				self::$outputPersistQueue[spl_object_id($reverseEntity)] = $reverseEntity;
			}
			$property->set(null, allowNull: true);
		} else {
			// $type === Relationship::ONE_HAS_MANY
			if ($reversePropertyMeta === null) {
				return;
			}

			$value = $entity->getValue($name);
			if ($value instanceof HasMany && $value->count() === 0) {
				return;
			}

			if ($reversePropertyMeta->isNullable) {
				$property = $entity->getProperty($name);
				assert($property instanceof IRelationshipCollection);
				foreach ($property as $subValue) {
					assert($subValue instanceof IEntity);
					if (!isset(self::$outputRemoveQueue[spl_object_id($subValue)])) {
						self::$outputPersistQueue[spl_object_id($subValue)] = $subValue;
					}
				}
				$property->set([]);
			} else {
				$entityClass = get_class($entity);
				$reverseEntityClass = $relationshipMeta->entity;
				$primaryValue = $entity->getValue('id');
				$primaryValue = is_array($primaryValue) ? '[' . implode(', ', $primaryValue) . ']' : $primaryValue;
				throw new InvalidStateException(
					"Cannot remove {$entityClass}::\$id={$primaryValue} because {$reverseEntityClass}::\${$reversePropertyMeta->name} cannot be a null.",
				);
			}
		}
	}
}
