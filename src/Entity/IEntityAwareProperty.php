<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


/**
 * @experimental This interface API is experimental and is subjected to change. It is ok to use its implementation.
 * @template E of IEntity
 */
interface IEntityAwareProperty extends IProperty
{
	/**
	 * Executed when the IProperty is attached to an entity.
	 * @param E $entity
	 */
	public function onEntityAttach(IEntity $entity): void;


	/**
	 * Executed when the entity is attached to the repository.
	 * @param E $entity
	 */
	public function onEntityRepositoryAttach(IEntity $entity): void;
}
