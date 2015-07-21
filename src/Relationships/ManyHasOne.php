<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;


class ManyHasOne extends HasOne
{

	protected function modify()
	{
		$this->isModified = TRUE;
		$this->parent->setAsModified($this->metadata->name);
	}


	protected function updateRelationship($oldEntity, $newEntity, $allowNull)
	{
		$this->updatingReverseRelationship = TRUE;
		$key = $this->metadata->relationship->property;

		if ($oldEntity) {
			$oldEntity->getValue($key)->remove($this->parent);
		}

		if ($newEntity) {
			$newEntity->getValue($key)->add($this->parent);
		}
		$this->updatingReverseRelationship = FALSE;
	}

}
