<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


class OneHasOne extends HasOne
{
	protected function createCollection(): ICollection
	{
		$colection = $this->getTargetRepository()->getMapper()->createCollectionOneHasOne($this->metadata);
		return $colection->setRelationshipParent($this->parent);
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
		if (!$key) {
			return;
		}

		$this->updatingReverseRelationship = true;
		if ($oldEntity) {
			$oldProperty = $oldEntity->getProperty($key);
			\assert($oldProperty instanceof OneHasOne);
			$oldProperty->set(null, $allowNull);
		}
		if ($newEntity) {
			$newProperty = $newEntity->getProperty($key);
			\assert($newProperty instanceof OneHasOne);
			$newProperty->set($this->parent, $allowNull);
		}
		$this->updatingReverseRelationship = false;
	}


	protected function initReverseRelationship(?IEntity $entity)
	{
		$key = $this->metadataRelationship->property;
		if (!$key || !$entity) {
			return;
		}

		$this->updatingReverseRelationship = true;
		$property = $entity->getProperty($key);
		\assert($property instanceof OneHasOne);
		$property->set($this->parent);
		$this->updatingReverseRelationship = false;
	}
}
