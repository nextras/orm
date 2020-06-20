<?php declare(strict_types = 1);

namespace Nextras\Orm\TestHelper;


use Nextras\Orm\Entity\IEntity;


trait TestCaseEntityTrait
{
	/**
	 * @param array<string, mixed> $parameters
	 */
	protected function e(string $entityClass, array $parameters = []): IEntity
	{
		return $this->container->getByType(EntityCreator::class)->create($entityClass, $parameters);
	}
}
