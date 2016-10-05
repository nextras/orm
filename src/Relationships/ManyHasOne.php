<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;


class ManyHasOne extends HasOne
{
	protected function createCollection()
	{
		return $this->getTargetRepository()->getMapper()->createCollectionManyHasOne($this->metadata, $this->parent);
	}


	protected function modify()
	{
		$this->isModified = true;
		$this->parent->setAsModified($this->metadata->name);
	}


	protected function updateRelationship($oldEntity, $newEntity, $allowNull)
	{
		$key = $this->metadata->relationship->property;
		if (!$key) {
			return;
		}

		$this->updatingReverseRelationship = true;
		if ($oldEntity) {
			$oldEntity->getValue($key)->remove($this->parent);
		}

		if ($newEntity) {
			$newEntity->getValue($key)->add($this->parent);
		}
		$this->updatingReverseRelationship = false;
	}


	protected function initReverseRelationship($newEntity)
	{
		$key = $this->metadata->relationship->property;
		if (!$key || !$newEntity) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$newEntity->getValue($key)->initReverseRelationship($this->parent);
		$this->updatingReverseRelationship = false;
	}
}
