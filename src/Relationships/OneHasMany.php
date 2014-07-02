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
