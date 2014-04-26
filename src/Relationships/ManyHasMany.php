<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\Collection\ArrayCollection;


class ManyHasMany extends HasMany implements IRelationshipCollection
{

	public function persist($recursive = TRUE)
	{
		$repository = $this->getTargetRepository();
		$toRemove = $toAdd = [];

		foreach ((array) $this->toRemove as $entity) {
			if (isset($entity->id)) {
				$toRemove[$entity->id] = $entity->id;
			}
		}

		if ($this->collection) {
			foreach ($this->collection as $entity) {
				if ($recursive || !isset($entity->id)) {
					$repository->persist($entity, $recursive);
				}
			}
		}

		foreach ((array) $this->toAdd as $entity) {
			if ($recursive || !isset($entity->id)) {
				$repository->persist($entity, $recursive);
			}
			$toAdd[$entity->id] = $entity->id;
		}

		if ($this->metadata->args[2]) {
			if ($toRemove) {
				$this->getMapper()->remove($toRemove);
			}
			if ($toAdd) {
				$this->getMapper()->add($toAdd);
			}
		}

		$this->toRemove = $this->toAdd = [];
		if ($this->collection instanceof ArrayCollection) {
			$this->collection = NULL;
		}
	}


	protected function createCollection()
	{
		return $this->parent->getRepository()->getMapper()->createCollectionManyHasMany($this->metadata, $this->parent);
	}


	protected function getMapper()
	{
		return $this->parent->getRepository()->getMapper()->getCollectionMapperManyHasMany($this->metadata);
	}

}
