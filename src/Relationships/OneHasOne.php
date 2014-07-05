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

	protected function updateRelationship($oldEntity, $newEntity)
	{
		$key = $this->propertyMeta->args[1];

		if ($oldEntity && isset($oldEntity->{$key}) && $oldEntity->{$key} === $this->parent) {
			$oldEntity->{$key} = NULL;
		}

		if ($newEntity && (!isset($newEntity->{$key}) || $newEntity->{$key} !== $this->parent)) {
			$newEntity->{$key} = $this->parent;
		}
	}

}
