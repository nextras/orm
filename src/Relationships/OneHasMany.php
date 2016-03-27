<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\IEntity;


class OneHasMany extends HasMany
{
	public function getEntitiesForPersistence()
	{
		$entities = [];
		foreach ($this->toAdd as $add) {
			$entities[] = $add;
		}
		foreach ($this->toRemove as $remove) {
			if ($remove->isPersisted()) {
				$entities[] = $remove;
			}
		}
		if ($this->collection !== null) {
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

		$this->collection = $this->getCollection(true);
		$this->toAdd = [];
		$this->toRemove = [];
		$this->isModified = false;
	}


	protected function modify()
	{
		$this->isModified = true;
	}


	protected function createCollection()
	{
		$collection = $this->getTargetRepository()->getMapper()->createCollectionOneHasMany($this->metadata, $this->parent);
		return $this->applyDefaultOrder($collection);
	}


	protected function updateRelationshipAdd(IEntity $entity)
	{
		if (!$this->metadata->relationship->property) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$entity->getProperty($this->metadata->relationship->property)->setInjectedValue($this->parent);
		$this->updatingReverseRelationship = false;
	}


	protected function updateRelationshipRemove(IEntity $entity)
	{
		if (!$this->metadata->relationship->property) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$entity->getProperty($this->metadata->relationship->property)->setInjectedValue(null);
		$this->updatingReverseRelationship = false;
	}
}
