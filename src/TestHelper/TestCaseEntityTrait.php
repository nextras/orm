<?php declare(strict_types = 1);

namespace Nextras\Orm\TestHelper;


trait TestCaseEntityTrait
{
	protected function e(string $entityClass, array $parameters = [])
	{
		return $this->container->getByType(EntityCreator::class)->create($entityClass, $parameters);
	}
}
