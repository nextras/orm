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

	public function persist($recursive = TRUE)
	{
		foreach ($this->toAdd as $add) {
			$this->getTargetRepository()->persist($add);
		}

		foreach ($this->toRemove as $remove) {
			$this->getTargetRepository()->persist($remove);
		}

		if ($this->collection !== NULL) {
			foreach ($this->collection as $entity) {
				$this->getTargetRepository()->persist($entity);
			}
		}

		$this->isModified = FALSE;
		$this->toRemove = $this->toAdd = [];
		if ($this->collection && $this->collection->getRelationshipMapper() === NULL) {
			$this->collection = NULL;
		}
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
		return $this->getTargetRepository()->getMapper()->createCollectionOneHasMany($this->metadata, $this->parent);
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
