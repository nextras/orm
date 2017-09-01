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
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Relationships\HasMany;
use Nextras\Orm\Relationships\HasOne;
use Nextras\Orm\Relationships\IRelationshipCollection;


class PersistenceHelper
{
	/** @var array */
	protected static $inputQueue = [];

	/** @var array */
	protected static $outputQueue = [];


	/**
	 * @see https://en.wikipedia.org/wiki/Topological_sorting#Depth-first_search
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


	protected static function visitEntity(IEntity $entity, IModel $model, bool $withCascade = true)
	{
		$entityHash = spl_object_hash($entity);
		if (isset(self::$outputQueue[$entityHash])) {
			if (self::$outputQueue[$entityHash] === true) {
				$cycle = [];
				$bt = debug_backtrace();
				foreach ($bt as $item) {
					if ($item['function'] === 'getCascadeQueue') {
						break;

					} elseif ($item['function'] === 'addRelationshipToQueue') {
						$cycle[] = get_class($item['args'][0]) . '::$' . $item['args'][1]->name;
					}
				}

				throw new InvalidStateException('Persist cycle detected in ' . implode(' - ', $cycle) . '. Use manual two phase persist.');
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


	protected static function visitRelationship(IRelationshipCollection $rel, IModel $model)
	{
		foreach ($rel->getEntitiesForPersistence() as $entity) {
			self::visitEntity($entity, $model);
		}

		self::$outputQueue[spl_object_hash($rel)] = $rel;
	}


	protected static function addRelationshipToQueue(IEntity $entity, PropertyMetadata $propertyMeta, IModel $model)
	{
		$isPersisted = $entity->isPersisted();
		$rawValue = $entity->getRawProperty($propertyMeta->name);
		if ($rawValue === null && ($propertyMeta->isNullable || $isPersisted)) {
			return;
		}

		$relationship = $entity->getProperty($propertyMeta->name);
		assert($relationship instanceof HasMany || $relationship instanceof HasOne);
		if (!$relationship->isLoaded() && $isPersisted) {
			return;
		}

		$value = $entity->getValue($propertyMeta->name);
		$rel = $propertyMeta->relationship;
		assert($rel !== null);
		if ($value instanceof IEntity && !$value->isPersisted() && ($rel->type !== Relationship::ONE_HAS_ONE || $rel->isMain)) {
			self::visitEntity($value, $model);

		} elseif ($value !== null) {
			self::$inputQueue[] = $value;
		}
	}
}
