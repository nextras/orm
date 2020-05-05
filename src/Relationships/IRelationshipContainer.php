<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityAwareProperty;
use Nextras\Orm\Entity\IPropertyContainer;


interface IRelationshipContainer extends IPropertyContainer, IEntityAwareProperty
{
	public function getEntity(): ?IEntity;


	/**
	 * Returns true if container was loaded.
	 */
	public function isLoaded(): bool;


	/**
	 * Returns true if relationship is modified.
	 */
	public function isModified(): bool;
}
