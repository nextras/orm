<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityAwareProperty;
use Nextras\Orm\Entity\IPropertyContainer;


/**
 * @template E of IEntity
 * @extends IEntityAwareProperty<E>
 */
interface IRelationshipContainer extends IPropertyContainer, IEntityAwareProperty
{
	/**
	 * @return E|null
	 */
	public function getEntity(): ?IEntity;


	/**
	 * Returns true if container was loaded, i.e. the relationship contains an entity in contrast to its primary
	 * key only.
	 */
	public function isLoaded(): bool;


	/**
	 * Returns true if relationship is modified.
	 */
	public function isModified(): bool;


	/**
	 * Returns IEntity for persistence.
	 * @return list<E>
	 * @ignore
	 * @internal
	 */
	public function getEntitiesForPersistence(): array;


	/**
	 * Returns immediate IEntity for Depth-first-search persistence.
	 * @return E|null
	 * @ignore
	 * @internal
	 */
	public function getImmediateEntityForPersistence(): ?IEntity;


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 */
	public function doPersist(): void;
}
