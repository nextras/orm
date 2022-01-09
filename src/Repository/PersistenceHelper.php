<?php declare(strict_types = 1);

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
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use function assert;


class PersistenceHelper
{
	/** @var array<int, IEntity|IRelationshipCollection> */
	protected static $inputQueue = [];

	/** @var array<string, IEntity|IRelationshipCollection|true> */
	protected static $outputQueue = [];


	/**
	 * @see https://en.wikipedia.org/wiki/Topological_sorting#Depth-first_search
	 * @return array<string, IEntity|IRelationshipCollection|true>
	 */
	public static function getCascadeQueue(IEntity $entity, IModel $model, bool $withCascade): array
	{
		try {
			self::visitEntity($entity, $model, $withCascade);

			for ($i = 0; $i < count(self::$inputQueue); $i++) {
				$value = self::$inputQueue[$i];
				if ($value instanceof IEntity) {
					self::visitEntity($value, $model);
				} else {
					self::visitRelationship($value, $model);
				}
			}

			return self::$outputQueue;
		} finally {
			self::$inputQueue = [];
			self::$outputQueue = [];
		}
	}


	protected static function visitEntity(IEntity $entity, IModel $model, bool $withCascade = true): void
	{
		$entityHash = spl_object_hash($entity);
		if (isset(self::$outputQueue[$entityHash])) {
			if (self::$outputQueue[$entityHash] === true) {
				$cycle = [];
				$bt = debug_backtrace();
				foreach ($bt as $item) {
					if ($item['function'] === 'getCascadeQueue') {
						break;
					} elseif ($item['function'] === 'addRelationshipToQueue' && isset($item['args'])) {
						$cycle[] = get_class($item['args'][0]) . '::$' . $item['args'][1]->name;
					}
				}
				$cycle = array_reverse($cycle);
				throw new InvalidStateException('Persist cycle detected in ' . implode(' - ', $cycle) . '. Use manual two-phase persist.');
			}
			return;
		}

		$repository = $model->getRepositoryForEntity($entity);
		$repository->attach($entity);
		$repository->onBeforePersist($entity);

		if ($withCascade) {
			self::$outputQueue[$entityHash] = true;
			foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
				if ($propertyMeta->relationship !== null && $propertyMeta->relationship->cascade['persist']) {
					self::addRelationshipToQueue($entity, $propertyMeta, $model);
				}
			}
			unset(self::$outputQueue[$entityHash]); // reenqueue
		}

		self::$outputQueue[$entityHash] = $entity;
	}


	protected static function visitRelationship(IRelationshipCollection $rel, IModel $model): void
	{
		foreach ($rel->getEntitiesForPersistence() as $entity) {
			self::visitEntity($entity, $model);
		}

		self::$outputQueue[spl_object_hash($rel)] = $rel;
	}


	protected static function addRelationshipToQueue(
		IEntity $entity,
		PropertyMetadata $propertyMeta,
		IModel $model
	): void
	{
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
			$relationshipEntity = $relationship->getEntity();
			if ($relationshipEntity === null) {
				return;
			}

			$metadataRel = $propertyMeta->relationship;
			assert($metadataRel !== null);

			if (!$relationshipEntity->isPersisted() && ($metadataRel->type === Relationship::MANY_HAS_ONE || $metadataRel->isMain)) {
				self::visitEntity($relationshipEntity, $model);
			} else {
				self::$inputQueue[] = $relationshipEntity;
			}
		} else {
			self::$inputQueue[] = $relationship;
		}
	}
}
