<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Nette\NotImplementedException;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\NotSupportedException;


class OneHasMany extends HasMany
{

	public function persist($recursive = TRUE, & $queue = NULL)
	{
		foreach ($this->toAdd as $add) {
			$this->getTargetRepository()->persist($add, $recursive, $queue);
		}

		foreach ($this->toRemove as $remove) {
			$this->getTargetRepository()->persist($remove, $recursive, $queue);
		}

		if ($this->collection !== NULL) {
			foreach ($this->collection as $entity) {
				$this->getTargetRepository()->persist($entity, $recursive, $queue);
			}
		}

		$this->toAdd = [];
		$this->toRemove = [];
		$this->collection = NULL;
		$this->isModified = FALSE;
	}


	public function getInjectedValue()
	{
		throw new NotSupportedException();
	}


	public function getStorableValue()
	{
		return NULL;
	}


	public function getRawValue()
	{
		throw new NotImplementedException();
	}


	protected function createCollection()
	{
		$collection = $this->getTargetRepository()->getMapper()->createCollectionOneHasMany($this->metadata, $this->parent);
		return $this->applyDefaultOrder($collection);
	}


	protected function updateRelationshipAdd(IEntity $entity)
	{
		$this->updatingReverseRelationship = TRUE;
		$entity->setValue($this->metadata->relationshipProperty, $this->parent);
		$this->updatingReverseRelationship = FALSE;
	}


	protected function updateRelationshipRemove(IEntity $entity)
	{
		$this->updatingReverseRelationship = TRUE;
		$entity->setValue($this->metadata->relationshipProperty, NULL);
		$this->updatingReverseRelationship = FALSE;
	}

}
