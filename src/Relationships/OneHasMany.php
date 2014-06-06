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
	}


	protected function createCollection()
	{
		$targetMapper = $this->getTargetRepository()->getMapper();
		return $targetMapper->createCollectionOneHasMany($targetMapper, $this->metadata, $this->parent);
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
