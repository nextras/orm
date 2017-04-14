<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nextras\Orm\Entity\IEntity;


interface IDependencyProvider
{
	/**
	 * Injects required dependencies into the entity.
	 */
	public function injectDependencies(IEntity $entity);
}
