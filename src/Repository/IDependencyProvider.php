<?php declare(strict_types = 1);

namespace Nextras\Orm\Repository;


use Nextras\Orm\Entity\IEntity;


interface IDependencyProvider
{
	/**
	 * Injects required dependencies into the entity.
	 */
	public function injectDependencies(IEntity $entity): void;
}
