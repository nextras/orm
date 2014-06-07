<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\TestHelper;


trait TestCaseEntityTrait
{

	protected function e($entityClass, array $parameters = [])
	{
		return $this->container->getByType('Nextras\Orm\TestHelper\EntityCreator')->create($entityClass, $parameters);
	}

}
