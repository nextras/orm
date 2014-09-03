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
use Nextras\Orm\NotImplementedException;


class ManyHasMany extends HasMany
{
	/** @var bool */
	protected $isPersisting = FALSE;


	public function persist($recursive = TRUE)
	{
		if ($this->isPersisting) {
			return;
		}

		$this->isPersisting = TRUE;
		$toRemove = $toAdd = [];

		foreach ((array) $this->toRemove as $entity) {
			if (isset($entity->id)) {
				$toRemove[$entity->id] = $entity->id;
			}
			unset($this->injectedValue[$entity->id]);
		}

		if ($this->collection) {
			foreach ($this->collection as $entity) {
				if ($recursive || !isset($entity->id)) {
					$this->getTargetRepository()->persist($entity, $recursive);
				}
			}
		}

		foreach ((array) $this->toAdd as $entity) {
			if ($recursive || !isset($entity->id)) {
				$this->getTargetRepository()->persist($entity, $recursive);
			}
			$toAdd[$entity->id] = $entity->id;
			$this->injectedValue[$entity->id] = $entity->id;
		}

		$this->toRemove = $this->toAdd = [];
		if ($this->collection && $this->collection->getRelationshipMapper() === NULL) {
			$this->collection = NULL;
		}

		if ($this->metadata->relationshipIsMain) {
			if ($toRemove) {
				$this->getCollection()->getRelationshipMapper()->remove($this->parent, $toRemove);
			}
			if ($toAdd) {
				$this->getCollection()->getRelationshipMapper()->add($this->parent, $toAdd);
			}
		}

		$this->isModified = FALSE;
		$this->isPersisting = FALSE;
	}


	public function getInjectedValue()
	{
		// is called only by Mapper\Memory\RMManyHasMany
		// and only if there is no unpersisted collection
		return $this->injectedValue;
	}


	public function getStorableValue()
	{
		return serialize($this->getInjectedValue());
	}


	public function getRawValue()
	{
		throw new NotImplementedException();
	}


	protected function createCollection()
	{
		if ($this->metadata->relationshipIsMain) {
			$mapperOne = $this->parent->getRepository()->getMapper();
			$mapperTwo = $this->getTargetRepository()->getMapper();
		} else {
			$mapperOne = $this->getTargetRepository()->getMapper();
			$mapperTwo = $this->parent->getRepository()->getMapper();
		}

		$collection = $mapperOne->createCollectionManyHasMany($mapperTwo, $this->metadata, $this->parent);
		if (isset($this->metadata->args->relationship['order'])) {
			return $collection->orderBy($this->metadata->args->relationship['order'][0], $this->metadata->args->relationship['order'][1]);
		} else {
			return $collection;
		}
	}


	protected function updateRelationshipAdd(IEntity $entity)
	{
		$otherSide = $entity->getProperty($this->metadata->relationshipProperty);
		$otherSide->collection = NULL;
		$otherSide->toAdd[spl_object_hash($this->parent)] = $this->parent;
	}


	protected function updateRelationshipRemove(IEntity $entity)
	{
		$otherSide = $entity->getProperty($this->metadata->relationshipProperty);
		$otherSide->collection = NULL;
		$otherSide->toRemove[spl_object_hash($this->parent)] = $this->parent;
	}

}
