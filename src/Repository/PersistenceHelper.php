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


/**
 * Builds the topologically sorted persist/remove queue for a cascade.
 * State is per call (no static properties), so interleaved calls from coroutines/fibers do not interfere.
 */
class PersistenceHelper
{
	/** @var array<int, IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity>> */
	protected array $inputQueue = [];

	/** @var array<int, IEntity|IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity>|true> */
	protected array $outputPersistQueue = [];

	/** @var array<int, IEntity|true> */
	protected array $outputRemoveQueue = [];


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
		return (new self())->build($entity, $mode, $model, $withCascade);
	}


	/**
	 * @return array{
	 *     array<int, IEntity|IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity>>,
	 *     array<int, IEntity>,
	 * }
	 */
	protected function build(IEntity $entity, PersistenceMode $mode, IModel $model, bool $withCascade): array
	{
		$this->visitEntity($entity, $mode, $model, $withCascade);

		for ($i = 0; $i < count($this->inputQueue); $i++) {
			$this->visitRelationship($this->inputQueue[$i], $mode, $model);
		}

		return [
			array_filter($this->outputPersistQueue, fn($val) => assert($val !== true)),
			array_filter($this->outputRemoveQueue, fn($val) => assert($val !== true)),
		];
	}


	protected function visitEntity(
		IEntity $entity,
		PersistenceMode $mode,
		IModel $model,
		bool $withCascade = true,
	): void
	{
		$entityId = spl_object_id($entity);
		$checked = match ($mode) {
			PersistenceMode::Persist => $this->outputPersistQueue[$entityId] ?? null,
			PersistenceMode::Remove => $this->outputRemoveQueue[$entityId] ?? null,
		};
		if ($checked !== null) {
			if ($checked === true) {
				$cycle = [];
				$bt = debug_backtrace();
				foreach ($bt as $item) {
					if ($item['function'] === 'getCascadeQueue') {
						break;
					} elseif ($item['function'] === 'enqueueRelationship' && isset($item['args'])) {
						$cycle[] = $item['args'][0]::class . '::$' . $item['args'][2]->name;
					}
				}
				$cycle = array_reverse($cycle);
				throw new InvalidStateException('Persist cycle detected in ' . implode(' - ', $cycle) . '. Use manual two-phase persist.');
			}
			return;
		}
		if ($mode === PersistenceMode::Persist && isset($this->outputRemoveQueue[$entityId])) {
			// this entity is already processed && scheduled for removal
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
			$this->outputRemoveQueue[$entityId] = true;
			foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
				if ($propertyMeta->relationship !== null) {
					$this->enqueueRelationship(
						entity: $entity,
						mode: $mode,
						propertyMeta: $propertyMeta,
						relationshipMeta: $propertyMeta->relationship,
						model: $model,
						nullOnly: !$withCascade,
					);
				}
			}
			unset($this->outputPersistQueue[$entityId]);
			unset($this->outputRemoveQueue[$entityId]); // re-enqueue
			$this->outputRemoveQueue[$entityId] = $entity;
		} elseif ($withCascade) {
			$this->outputPersistQueue[$entityId] = true;
			foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
				if ($propertyMeta->relationship !== null) {
					$this->enqueueRelationship(
						entity: $entity,
						mode: $mode,
						propertyMeta: $propertyMeta,
						relationshipMeta: $propertyMeta->relationship,
						model: $model,
					);
				}
			}
			unset($this->outputPersistQueue[$entityId]); // re-enqueue
			$this->outputPersistQueue[$entityId] = $entity;
		} else {
			$this->outputPersistQueue[$entityId] = $entity;
		}
	}


	/**
	 * @param IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity> $rel
	 */
	protected function visitRelationship(
		IRelationshipContainer|IRelationshipCollection $rel,
		PersistenceMode $mode,
		IModel $model,
	): void
	{
		foreach ($rel->getEntitiesForPersistence() as $entity) {
			$this->visitEntity($entity, $mode, $model);
		}

		$this->outputPersistQueue[spl_object_id($rel)] = $rel;
	}


	protected function enqueueRelationship(
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
			if (
				$relationship === null
				|| (!($relationship instanceof IRelationshipCollection
					|| $relationship instanceof IRelationshipContainer)
					&& $isPersisted)
			) {
				// 1. relationship is not initialized at all
				// 2. relationship has a scalar value and the entity is persisted -> no change
				return;
			}

			assert($relationship instanceof IRelationshipCollection || $relationship instanceof IRelationshipContainer);
			if (!$relationship->isLoaded() && $isPersisted) {
				return;
			}

			if ($relationship instanceof IRelationshipCollection) {
				$toRemoveFromRelationship = $relationship->getEntitiesForRemoval();
				if ($toRemoveFromRelationship !== []) {
					if ($relationshipMeta->cascade['removeOrphan'] !== true) {
						$entityClass = $entity::class;
						$entityId = json_encode($entity->getValue('id'));
						$propertyName = $propertyMeta->name;
						throw new InvalidStateException(
							"The {$entityClass}[id=$entityId]::\$$propertyName relationship changed and the removed "
							. "entity(ies) cannot be persisted as its relationship's side is non-nullable. "
							. 'Consider enabling `removeOrphan` cascade.',
						);
					}
					foreach ($toRemoveFromRelationship as $subEntity) {
						$this->visitEntity($subEntity, PersistenceMode::Remove, $model);
					}
				}
			}

			if ($relationship instanceof IRelationshipContainer) {
				$immediateEntity = $relationship->getImmediateEntityForPersistence();
				if ($immediateEntity !== null) {
					$this->visitEntity($immediateEntity, $mode, $model);
				}
			}

			$this->inputQueue[] = $relationship;

		} else {
			if ($relationshipMeta->cascade['remove'] !== true) {
				$this->nullRelationship($entity, $propertyMeta, $relationshipMeta, $model);
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
				$value = $relationship->getEntity();
				if ($value !== null) {
					if ($relationshipMeta->type === Relationship::ONE_HAS_ONE && !$relationshipMeta->isMain) {
						$this->visitEntity($value, $mode, $model);
					} else {
						$this->inputQueue[] = $relationship;
					}
				}
			} elseif ($relationship instanceof IRelationshipCollection) {
				foreach ($relationship->getIterator() as $subValue) {
					$this->visitEntity($subValue, $mode, $model);
				}
				$this->outputPersistQueue[spl_object_id($relationship)] = $relationship;
			}
		}
	}


	protected function nullRelationship(
		IEntity $entity,
		PropertyMetadata $propertyMeta,
		PropertyRelationshipMetadata $relationshipMeta,
		IModel $model,
	): void
	{
		$type = $relationshipMeta->type;
		$name = $propertyMeta->name;

		if (!$entity->hasValue($name)) {
			return;
		}

		$value = $entity->getValue($name);
		if ($value instanceof HasMany && $value->count() === 0) {
			return;
		}

		$reverseRepository = $model->getRepository($relationshipMeta->repository);
		$reversePropertyMeta = $relationshipMeta->property !== null
			? $reverseRepository
				->getEntityMetadata($relationshipMeta->entity)
				->getProperty($relationshipMeta->property)
			: null;

		if ($type === Relationship::MANY_HAS_MANY) {
			/** @var ManyHasMany<IEntity> $property */
			$property = $entity->getProperty($name);
			assert($property instanceof ManyHasMany);
			$this->outputPersistQueue[spl_object_id($property)] = $property;
			if ($reversePropertyMeta !== null) {
				foreach ($property as $reverseEntity) {
					/** @var ManyHasMany<IEntity> $reverseRelationship */
					$reverseRelationship = $reverseEntity->getProperty($reversePropertyMeta->name);
					$this->outputPersistQueue[spl_object_id($reverseRelationship)] = $reverseRelationship;
				}
			}
			$property->set([]);
		} elseif ($type === Relationship::MANY_HAS_ONE || $type === Relationship::ONE_HAS_ONE) {
			$property = $entity->getProperty($name);
			assert($property instanceof HasOne);
			if ($reversePropertyMeta !== null) {
				$reverseEntity = $property->getEntity();
				if ($reverseEntity === null || isset($this->outputRemoveQueue[spl_object_id($reverseEntity)])) {
					// The reverse side is also being removed, do not set null to this relationship.
					return;
				}
				/** @var HasOne<IEntity> $reverseRelationship */
				$reverseRelationship = $reverseEntity->getProperty($reversePropertyMeta->name);
				$this->outputPersistQueue[spl_object_id($reverseRelationship)] = $reverseRelationship;
				$this->outputPersistQueue[spl_object_id($reverseEntity)] = $reverseEntity;
			}
			$property->set(null, allowNull: true);
		} else {
			// $type === Relationship::ONE_HAS_MANY
			if ($reversePropertyMeta === null) {
				return;
			}

			if ($reversePropertyMeta->isNullable) {
				$property = $entity->getProperty($name);
				assert($property instanceof IRelationshipCollection);
				foreach ($property as $subValue) {
					if (!isset($this->outputRemoveQueue[spl_object_id($subValue)])) {
						$this->outputPersistQueue[spl_object_id($subValue)] = $subValue;
					}
				}
				$property->set([]);
			} else {
				$entityClass = $entity::class;
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
