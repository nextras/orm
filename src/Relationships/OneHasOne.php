<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use function assert;


class OneHasOne extends HasOne
{
	protected function createCollection(): ICollection
	{
		$collection = $this->getTargetRepository()->getMapper()->createCollectionOneHasOne($this->metadata);
		return $collection->setRelationshipParent($this->parent);
	}


	public function getRawValue()
	{
		if ($this->primaryValue === null && $this->value === false && !$this->metadataRelationship->isMain) {
			$this->getEntity(); // init the value
		}
		return parent::getRawValue();
	}


	public function hasInjectedValue(): bool
	{
		if ($this->primaryValue === null && $this->value === false && !$this->metadataRelationship->isMain) {
			return $this->fetchValue() !== null;
		}
		return parent::hasInjectedValue();
	}


	protected function modify(): void
	{
		$this->isModified = true;
		if ($this->metadataRelationship->isMain) {
			$this->parent->setAsModified($this->metadata->name);
		}
	}


	protected function updateRelationship(?IEntity $oldEntity, ?IEntity $newEntity, bool $allowNull): void
	{
		$key = $this->metadataRelationship->property;
		if ($key === null) {
			return;
		}

		$this->updatingReverseRelationship = true;
		if ($oldEntity !== null) {
			$oldProperty = $oldEntity->getProperty($key);
			assert($oldProperty instanceof OneHasOne);
			$oldProperty->set(null, $allowNull);
		}
		if ($newEntity !== null) {
			$newProperty = $newEntity->getProperty($key);
			assert($newProperty instanceof OneHasOne);
			$newProperty->set($this->parent, $allowNull);
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
		assert($property instanceof OneHasOne);
		$property->set($this->parent);
		$this->updatingReverseRelationship = false;
	}
}
