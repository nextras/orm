<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


/**
 * @experimental This interface API is experimental and is subjected to change. It is ok to use its implementation.
 */
interface IEntityAwareProperty extends IProperty
{
	/**
	 * this listener is ired when property is attached to entity.
	 */
	public function onEntityAttach(IEntity $entity): void;


	/**
	 * This listener is fired when the entity is attached to repository.
	 */
	public function onEntityRepositoryAttach(IEntity $entity): void;
}
