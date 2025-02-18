<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use function assert;


/**
 * @template E of IEntity
 * @extends HasOne<E>
 */
class ManyHasOne extends HasOne
{
	protected function createCollection(): ICollection
	{
		/** @var ICollection<E> $collection */
		$collection = $this->getTargetRepository()->getMapper()->createCollectionManyHasOne($this->metadata);
		return $collection->setRelationshipParent($this->getParentEntity());
	}


	protected function modify(): void
	{
		$this->isModified = true;
		$this->getParentEntity()->setAsModified($this->metadata->name);
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
			$property->remove($this->getParentEntity());
		}

		if ($newEntity !== null) {
			$property = $newEntity->getProperty($key);
			assert($property instanceof OneHasMany);
			$property->add($this->getParentEntity());
		}
		$this->updatingReverseRelationship = false;
	}


	protected function initReverseRelationship(?IEntity $currentEntity): void
	{
		$key = $this->metadataRelationship->property;
		if ($key === null || $currentEntity === null) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$property = $currentEntity->getProperty($key);
		assert($property instanceof OneHasMany);
		$property->trackEntity($this->getParentEntity());
		$this->updatingReverseRelationship = false;
	}


	protected function isImmediateEntityForPersistence(?IEntity $entity): bool
	{
		return $entity !== null && !$entity->isPersisted();
	}
}
