<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\Container;
use Nextras\Orm\Model\IRepositoryLoader;


class RepositoryLoader implements IRepositoryLoader
{
	/** @var Container */
	private $container;

	/** @var array */
	private $repositoryNamesMap;


	public function __construct(Container $container, array $repositoryNamesMap)
	{
		$this->container = $container;
		$this->repositoryNamesMap = $repositoryNamesMap;
	}


	public function hasRepository($className)
	{
		return isset($this->repositoryNamesMap[$className]);
	}


	public function getRepository($className)
	{
		return $this->container->getService($this->repositoryNamesMap[$className]);
	}


	public function isCreated($className)
	{
		return $this->container->isCreated($this->repositoryNamesMap[$className]);
	}
}
