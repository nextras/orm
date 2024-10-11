<?php declare(strict_types = 1);

namespace Nextras\Orm\Repository;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use function assert;


class PersistenceHelper
{
	/** @var array<int, IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity>> */
	protected static array $inputQueue = [];

	/** @var array<int, IEntity|IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity>|true> */
	protected static array $outputQueue = [];


	/**
	 * @see https://en.wikipedia.org/wiki/Topological_sorting#Depth-first_search
	 * @return array<int, IEntity|IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity>|true>
	 */
	public static function getCascadeQueue(IEntity $entity, IModel $model, bool $withCascade): array
	{
		try {
			self::visitEntity($entity, $model, $withCascade);

			for ($i = 0; $i < count(self::$inputQueue); $i++) {
				$value = self::$inputQueue[$i];
				self::visitRelationship($value, $model);
			}

			return self::$outputQueue;
		} finally {
			self::$inputQueue = [];
			self::$outputQueue = [];
		}
	}


	protected static function visitEntity(IEntity $entity, IModel $model, bool $withCascade = true): void
	{
		$entityId = spl_object_id($entity);
		if (isset(self::$outputQueue[$entityId])) {
			if (self::$outputQueue[$entityId] === true) {
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
			self::$outputQueue[$entityId] = true;
			foreach ($entity->getMetadata()->getProperties() as $propertyMeta) {
				if ($propertyMeta->relationship !== null && $propertyMeta->relationship->cascade['persist']) {
					self::addRelationshipToQueue($entity, $propertyMeta, $model);
				}
			}
			unset(self::$outputQueue[$entityId]); // reenqueue
		}

		self::$outputQueue[$entityId] = $entity;
	}


	/**
	 * @param IRelationshipCollection<IEntity>|IRelationshipContainer<IEntity> $rel
	 */
	protected static function visitRelationship($rel, IModel $model): void
	{
		foreach ($rel->getEntitiesForPersistence() as $entity) {
			self::visitEntity($entity, $model);
		}

		self::$outputQueue[spl_object_id($rel)] = $rel;
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
			$immediateEntity = $relationship->getImmediateEntityForPersistence();
			if ($immediateEntity !== null) {
				self::visitEntity($immediateEntity, $model);
			}
		}

		self::$inputQueue[] = $relationship;
	}
}
