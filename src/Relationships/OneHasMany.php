<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use function assert;


class OneHasMany extends HasMany
{
	public function getEntitiesForPersistence(): array
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


	public function doPersist(): void
	{
		if (!$this->isModified) {
			return;
		}

		$this->tracked += $this->toAdd;
		foreach ($this->toRemove as $hash => $entity) {
			unset($this->tracked[$hash]);
		}
		$this->toAdd = [];
		$this->toRemove = [];

		$this->isModified = false;
		$this->collection = null;

		$this->getRelationshipMapper()->clearCache();
		$this->relationshipMapper = null;
	}


	protected function modify(): void
	{
		$this->isModified = true;
	}


	protected function createCollection(): ICollection
	{
		/** @phpstan-var callable(\Traversable<mixed,IEntity>):void $subscribeCb */
		$subscribeCb = function ($entities): void {
			foreach ($entities as $entity) {
				$this->trackEntity($entity);
			}
		};

		$collection = $this->getTargetRepository()->getMapper()->createCollectionOneHasMany($this->metadata);
		$collection = $collection->setRelationshipParent($this->parent);
		$collection->subscribeOnEntityFetch($subscribeCb);
		return $this->applyDefaultOrder($collection);
	}


	protected function updateRelationshipAdd(IEntity $entity): void
	{
		if ($this->metadataRelationship->property === null) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$property = $entity->getProperty($this->metadataRelationship->property);
		assert($property instanceof ManyHasOne);
		$property->set($this->parent);
		$this->updatingReverseRelationship = false;
	}


	protected function updateRelationshipRemove(IEntity $entity): void
	{
		if ($this->metadataRelationship->property === null) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$property = $entity->getProperty($this->metadataRelationship->property);
		assert($property instanceof ManyHasOne);
		$property->set(null, true);
		$this->updatingReverseRelationship = false;
	}
}
