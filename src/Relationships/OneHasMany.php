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
		if ($this->collection !== null || $this->wasLoaded) {
			return iterator_to_array($this->getIterator());

		} else {
			$entities = $this->added + $this->toAdd;

			foreach ($this->toRemove as $hash => $remove) {
				if ($remove->isPersisted()) {
					$entities[$hash] = $remove;
				} else {
					unset($entities[$hash]);
				}
			}
			return $entities;
		}
	}


	public function doPersist()
	{
		if (!$this->isModified) {
			return;
		}

		$this->added += $this->toAdd;
		$this->removed += $this->toRemove;
		$this->toAdd = [];
		$this->toRemove = [];
		$this->isModified = false;
		$this->collection = null;
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
