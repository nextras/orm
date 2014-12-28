<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Repository;


use Nextras\Orm\Entity\IEntity;


interface IDependencyProvider
{

	/**
	 * Injects required dependencies into the entity.
	 * @param  IEntity $entity
	 */
	public function injectDependencies(IEntity $entity);

}
