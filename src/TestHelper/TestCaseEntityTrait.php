<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\TestHelper;


trait TestCaseEntityTrait
{
	protected function e(string $entityClass, array $parameters = [])
	{
		return $this->container->getByType(EntityCreator::class)->create($entityClass, $parameters);
	}
}
