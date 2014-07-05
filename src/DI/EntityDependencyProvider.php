<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\DI;

use Nette\DI\Container;
use Nette\Object;
use Nextras\Orm\Entity\IEntity;


class EntityDependencyProvider extends Object
{
	/** @var Container */
	private $container;


	public function __construct(Container $container)
	{
		$this->container = $container;
	}


	public function injectDependencies(IEntity $entity)
	{
		$this->container->callInjects($entity);
		return $entity;
	}

}
