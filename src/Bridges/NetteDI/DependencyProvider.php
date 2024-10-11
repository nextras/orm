<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\Container;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IDependencyProvider;


class DependencyProvider implements IDependencyProvider
{
	public function __construct(
		private readonly Container $container,
	)
	{
	}


	public function injectDependencies(IEntity $entity): void
	{
		$this->container->callInjects($entity);
	}
}
