<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


/**
 * @experimental This interface API is experimental and is subjected to change. It is ok to use its implementation.
 */
interface IEntityAwareProperty extends IProperty
{
	public function setPropertyEntity(IEntity $entity): void;
}
