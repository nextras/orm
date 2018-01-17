<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\RelationshipMapperOneHasMany;
use Traversable;

class OneHasMany extends HasMany
{
	/**
	 * @return       IEntity[]
	 */
	public function getEntitiesForPersistence()
	{
		$entities = $this->tracked + $this->toAdd;

		foreach ($this->toRemove as $hash => $remove) {
			if ($remove->isPersisted()) {
				$entities[$hash] = $remove;
			} else {
				unset($entities[$hash]);
			}
		}
		return $entities;
	}


	/**
	 * @return void
	 */
	public function doPersist()
	{
		if (!$this->isModified) {
			return;
		}

		$this->tracked += $this->toAdd;
		$this->toAdd = [];
		$this->toRemove = [];
		$this->isModified = false;
		$this->collection = null;
		/** @var RelationshipMapperOneHasMany */
		$relationshipMapper = $this->getRelationshipMapper();
		$relationshipMapper->clearCache();
	}


	protected function modify()
	{
		$this->isModified = true;
	}


	protected function createCollection(): ICollection
	{
		$collection = $this->getTargetRepository()->getMapper()->createCollectionOneHasMany($this->metadata);
		$collection = $collection->setRelationshipParent($this->parent);
		$collection->subscribeOnEntityFetch(/**
		 * @return void
		 */
		function (Traversable $entities) {
			foreach ($entities as $entity) {
				$this->trackEntity($entity);
			}
		});
		return $this->applyDefaultOrder($collection);
	}


	protected function updateRelationshipAdd(IEntity $entity)
	{
		if (!$this->metadata->relationship->property) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$entity->getProperty($this->metadata->relationship->property)->setInjectedValue($this->parent);
		$this->updatingReverseRelationship = false;
	}


	protected function updateRelationshipRemove(IEntity $entity)
	{
		if (!$this->metadata->relationship->property) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$entity->getProperty($this->metadata->relationship->property)->setInjectedValue(null);
		$this->updatingReverseRelationship = false;
	}
}
