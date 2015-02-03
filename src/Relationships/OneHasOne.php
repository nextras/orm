<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;


class OneHasOne extends HasOne
{

	protected function updateRelationship($oldEntity, $newEntity, $allowNull)
	{
		$this->updatingReverseRelationship = TRUE;
		$key = $this->propertyMeta->relationshipProperty;

		if ($oldEntity && $oldEntity->hasValue($key) && $oldEntity->getValue($key) === $this->parent) {
			$oldEntity->setValue($key, NULL);
		}

		if ($newEntity && (!$newEntity->hasValue($key) || $newEntity->getValue($key) !== $this->parent)) {
			$newEntity->setValue($key, $this->parent);
		}

		$this->updatingReverseRelationship = FALSE;
	}

}
