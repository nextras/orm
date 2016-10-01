<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\IEntity;


class ManyHasMany extends HasMany
{
	public function getEntitiesForPersistence()
	{
		$entities = [];
		foreach ($this->toAdd as $entity) {
			$entities[] = $entity;
		}
		if ($this->collection) {
			foreach ($this->getIterator() as $entity) {
				$entities[] = $entity;
			}
		}
		return $entities;
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

		$this->collection = $this->getCollection(true);
		$this->toAdd = [];
		$this->toRemove = [];
		$this->isModified = false;

		if ($this->metadata->relationship->isMain) {
			$this->getRelationshipMapper()->remove($this->parent, $toRemove);
			$this->getRelationshipMapper()->add($this->parent, $toAdd);
		}
	}


	protected function modify()
	{
		$this->isModified = true;
	}


	protected function createCollection()
	{
		if ($this->metadata->relationship->isMain) {
			$mapperOne = $this->parent->getRepository()->getMapper();
			$mapperTwo = $this->getTargetRepository()->getMapper();
		} else {
			$mapperOne = $this->getTargetRepository()->getMapper();
			$mapperTwo = $this->parent->getRepository()->getMapper();
		}

		$collection = $mapperOne->createCollectionManyHasMany($mapperTwo, $this->metadata, $this->parent);
		return $this->applyDefaultOrder($collection);
	}


	protected function updateRelationshipAdd(IEntity $entity)
	{
		if (!$this->metadata->relationship->property) {
			return;
		}

		$otherSide = $entity->getProperty($this->metadata->relationship->property);
		$otherSide->collection = null;
		$otherSide->toAdd[spl_object_hash($this->parent)] = $this->parent;
		$otherSide->modify();
	}


	protected function updateRelationshipRemove(IEntity $entity)
	{
		if (!$this->metadata->relationship->property) {
			return;
		}

		$otherSide = $entity->getProperty($this->metadata->relationship->property);
		$otherSide->collection = null;
		$otherSide->toRemove[spl_object_hash($this->parent)] = $this->parent;
		$otherSide->modify();
	}
}
