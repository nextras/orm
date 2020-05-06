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
use function assert;


class ManyHasMany extends HasMany
{
	public function getEntitiesForPersistence()
	{
		return $this->tracked + $this->toAdd + $this->toRemove;
	}


	public function doPersist()
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
			$this->getRelationshipMapper()->remove($this->parent, $toRemove);
			$this->getRelationshipMapper()->add($this->parent, $toAdd);
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
		if ($this->metadataRelationship->isMain) {
			$mapperOne = $this->parent->getRepository()->getMapper();
			$mapperTwo = $this->getTargetRepository()->getMapper();
		} else {
			$mapperOne = $this->getTargetRepository()->getMapper();
			$mapperTwo = $this->parent->getRepository()->getMapper();
		}

		$collection = $mapperOne->createCollectionManyHasMany($mapperTwo, $this->metadata);
		$collection = $collection->setRelationshipParent($this->parent);
		$collection->subscribeOnEntityFetch(function (Traversable $entities) {
			if (!$this->metadataRelationship->property) {
				return;
			}
			foreach ($entities as $entity) {
				$entity->getProperty($this->metadataRelationship->property)->trackEntity($this->parent);
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
		$otherSide = $entity->getProperty($this->metadataRelationship->property);
		assert($otherSide instanceof ManyHasMany);
		$otherSide->add($this->parent);
		$this->updatingReverseRelationship = false;
	}


	protected function updateRelationshipRemove(IEntity $entity): void
	{
		if (!$this->metadataRelationship->property) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$otherSide = $entity->getProperty($this->metadataRelationship->property);
		assert($otherSide instanceof ManyHasMany);
		$otherSide->remove($this->parent);
		$this->updatingReverseRelationship = false;
	}
}
