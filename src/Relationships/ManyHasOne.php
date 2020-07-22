<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use function assert;


class ManyHasOne extends HasOne
{
	protected function createCollection(): ICollection
	{
		$collection = $this->getTargetRepository()->getMapper()->createCollectionManyHasOne($this->metadata);
		return $collection->setRelationshipParent($this->parent);
	}


	protected function modify(): void
	{
		$this->isModified = true;
		$this->parent->setAsModified($this->metadata->name);
	}


	protected function updateRelationship(?IEntity $oldEntity, ?IEntity $newEntity, bool $allowNull): void
	{
		$key = $this->metadataRelationship->property;
		if ($key === null) {
			return;
		}

		$this->updatingReverseRelationship = true;
		if ($oldEntity !== null) {
			$property = $oldEntity->getProperty($key);
			assert($property instanceof OneHasMany);
			$property->remove($this->parent);
		}

		if ($newEntity !== null) {
			$property = $newEntity->getProperty($key);
			assert($property instanceof OneHasMany);
			$property->add($this->parent);
		}
		$this->updatingReverseRelationship = false;
	}


	protected function initReverseRelationship(?IEntity $entity): void
	{
		$key = $this->metadataRelationship->property;
		if ($key === null || $entity === null) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$property = $entity->getProperty($key);
		assert($property instanceof OneHasMany);
		$property->trackEntity($this->parent);
		$this->updatingReverseRelationship = false;
	}
}
