<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\Container;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IDependencyProvider;


class DependencyProvider implements IDependencyProvider
{
	/** @var Container */
	private $container;


	public function __construct(Container $container)
	{
		$this->container = $container;
	}


	public function injectDependencies(IEntity $entity): IEntity
	{
		$this->container->callInjects($entity);
		return $entity;
	}
}
