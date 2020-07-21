<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapperManyHasMany;
use Traversable;
use function array_values;
use function assert;


class ManyHasMany extends HasMany
{
	public function getEntitiesForPersistence(): array
	{
		return $this->tracked + $this->toAdd + $this->toRemove;
	}


	public function doPersist(): void
	{
		if (!$this->isModified) {
			return;
		}

		$toRemove = [];
		foreach ($this->toRemove as $entity) {
			$id = $entity->getValue('id');
			$toRemove[$id] = $id;
		}
		$toAdd = [];
		foreach ($this->toAdd as $entity) {
			$id = $entity->getValue('id');
			$toAdd[$id] = $id;
		}

		$this->tracked += $this->toAdd;
		$this->toAdd = [];
		$this->toRemove = [];
		$this->isModified = false;
		$this->collection = null;

		if ($this->metadataRelationship->isMain) {
			$relationshipMapper = $this->getRelationshipMapper();
			assert($relationshipMapper instanceof IRelationshipMapperManyHasMany);
			$relationshipMapper->remove($this->parent, array_values($toRemove));
			$relationshipMapper->add($this->parent, array_values($toAdd));
		}

		$this->getRelationshipMapper()->clearCache();
		$this->relationshipMapper = null;
	}


	protected function modify(): void
	{
		$this->isModified = true;
	}


	protected function createCollection(): ICollection
	{
		/** @phpstan-var callable(Traversable<mixed,IEntity>):void $subscribeCb */
		$subscribeCb = function (Traversable $entities): void {
			if ($this->metadataRelationship->property === null) {
				return;
			}
			foreach ($entities as $entity) {
				$entity->getProperty($this->metadataRelationship->property)->trackEntity($this->parent);
				$this->trackEntity($entity);
			}
		};
		$mapper = $this->parent->getRepository()->getMapper();

		$collection = $this->getTargetRepository()->getMapper()->createCollectionManyHasMany($mapper, $this->metadata);
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
		$otherSide = $entity->getProperty($this->metadataRelationship->property);
		assert($otherSide instanceof ManyHasMany);
		$otherSide->add($this->parent);
		$this->updatingReverseRelationship = false;
	}


	protected function updateRelationshipRemove(IEntity $entity): void
	{
		if ($this->metadataRelationship->property === null) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$otherSide = $entity->getProperty($this->metadataRelationship->property);
		assert($otherSide instanceof ManyHasMany);
		$otherSide->remove($this->parent);
		$this->updatingReverseRelationship = false;
	}
}
