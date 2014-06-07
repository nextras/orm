<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\IEntity;


class OneHasMany extends HasMany implements IRelationshipCollection
{

	public function persist($recursive = TRUE)
	{
		// relations are stored in entites
		// todo: persist entites when method is called directly

		foreach ((array) $this->toRemove as $entity) {
			unset($this->injectedValue[$entity->id]);
		}
		foreach ((array) $this->toAdd as $entity) {
			$this->injectedValue[$entity->id] = $entity->id;
		}

		$this->toRemove = $this->toAdd = [];
		if ($this->collection && $this->collection->getRelationshipMapper() === NULL) {
			$this->collection = NULL;
		}
	}


	protected function createCollection()
	{
		return $this->getTargetRepository()->getMapper()->createCollectionOneHasMany($this->metadata, $this->parent);
	}


	protected function updateRelationshipAdd(IEntity $entity)
	{
		$this->updatingReverseRelationship = TRUE;
		$entity->{$this->metadata->args[1]} = $this->parent;
		$this->updatingReverseRelationship = FALSE;
	}


	protected function updateRelationshipRemove(IEntity $entity)
	{
		$this->updatingReverseRelationship = TRUE;
		$entity->{$this->metadata->args[1]} = NULL;
		$this->updatingReverseRelationship = FALSE;
	}

}
