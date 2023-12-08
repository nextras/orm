<?php declare(strict_types = 1);

namespace Nextras\Orm\TestHelper;


use Nextras\Orm\Entity\IEntity;


trait TestCaseEntityTrait
{
	/**
	 * @template T of IEntity
	 * @param class-string<T> $entityClass
	 * @param array<string, mixed> $parameters
	 * @return T
	 */
	protected function e(string $entityClass, array $parameters = []): IEntity
	{
		return $this->container->getByType(EntityCreator::class)->create($entityClass, $parameters);
	}
}
