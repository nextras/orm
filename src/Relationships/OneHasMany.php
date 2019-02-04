<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Traversable;


class OneHasMany extends HasMany
{
	public function getEntitiesForPersistence()
	{
		$entities = $this->tracked + $this->toAdd;

		foreach ($this->toRemove as $hash => $remove) {
			if ($remove->isPersisted()) {
				$entities[$hash] = $remove;
			} else {
				unset($entities[$hash]);
			}
		}
		return $entities;
	}


	public function doPersist()
	{
		if (!$this->isModified) {
			return;
		}

		$this->tracked += $this->toAdd;
		$this->toAdd = [];
		$this->toRemove = [];
		$this->isModified = false;
		$this->collection = null;
		$this->getRelationshipMapper()->clearCache();
	}


	protected function modify(): void
	{
		$this->isModified = true;
	}


	protected function createCollection(): ICollection
	{
		$collection = $this->getTargetRepository()->getMapper()->createCollectionOneHasMany($this->metadata);
		$collection = $collection->setRelationshipParent($this->parent);
		$collection->subscribeOnEntityFetch(function (Traversable $entities) {
			foreach ($entities as $entity) {
				$this->trackEntity($entity);
			}
		});
		return $this->applyDefaultOrder($collection);
	}


	protected function updateRelationshipAdd(IEntity $entity): void
	{
		if (!$this->metadataRelationship->property) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$entity->getProperty($this->metadataRelationship->property)->setInjectedValue($entity, $this->parent);
		$this->updatingReverseRelationship = false;
	}


	protected function updateRelationshipRemove(IEntity $entity): void
	{
		if (!$this->metadataRelationship->property) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$entity->getProperty($this->metadataRelationship->property)->setInjectedValue($entity, null);
		$this->updatingReverseRelationship = false;
	}
}
