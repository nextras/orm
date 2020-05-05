<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


interface IEntityAwareProperty extends IProperty
{
	public function setPropertyEntity(IEntity $entity): void;
}
