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

	public function persist($recursive = TRUE, & $queue = NULL)
	{
		foreach ($this->toAdd as $add) {
			$this->getTargetRepository()->persist($add, $recursive, $queue);
		}

		foreach ($this->toRemove as $remove) {
			if ($remove->isPersisted()) {
				$this->getTargetRepository()->persist($remove, $recursive, $queue);
			}
		}

		if ($this->collection !== NULL) {
			foreach ($this->getIterator() as $entity) {
				$this->getTargetRepository()->persist($entity, $recursive, $queue);
			}
		}

		$this->toAdd = [];
		$this->toRemove = [];
		$this->collection = NULL;
		$this->isModified = FALSE;
	}


	protected function modify()
	{
		$this->isModified = TRUE;
	}


	protected function createCollection()
	{
		$collection = $this->getTargetRepository()->getMapper()->createCollectionOneHasMany($this->metadata, $this->parent);
		return $this->applyDefaultOrder($collection);
	}


	protected function updateRelationshipAdd(IEntity $entity)
	{
		$this->updatingReverseRelationship = TRUE;
		$entity->setValue($this->metadata->relationship->property, $this->parent);
		$this->updatingReverseRelationship = FALSE;
	}


	protected function updateRelationshipRemove(IEntity $entity)
	{
		$this->updatingReverseRelationship = TRUE;
		$entity->setValue($this->metadata->relationship->property, NULL);
		$this->updatingReverseRelationship = FALSE;
	}

}
