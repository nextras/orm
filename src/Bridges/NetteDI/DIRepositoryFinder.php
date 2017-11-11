<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\ContainerBuilder;
use Nette\DI\ServiceDefinition;
use Nextras\Orm\Repository\IRepository;


class DIRepositoryFinder implements IRepositoryFinder
{
	/** @var ContainerBuilder */
	private $builder;

	/** @var OrmExtension */
	private $extension;


	public function __construct(string $modelClass, ContainerBuilder $containerBuilder, OrmExtension $extension)
	{
		$this->builder = $containerBuilder;
		$this->extension = $extension;
	}


	public function loadConfiguration()
	{
		return null;
	}


	public function beforeCompile()
	{
		$types = $this->builder->findByType(IRepository::class);
		$repositories = [];
		$repositoriesMap = [];
		foreach ($types as $serviceName => $serviceDefinition) {
			$serviceDefinition->addSetup('setModel', [$this->extension->prefix('@model')]);
			$class = $serviceDefinition->getClass();
			assert($class !== null);
			$name = $this->getRepositoryName($serviceName, $serviceDefinition);
			$repositories[$name] = $class;
			$repositoriesMap[$class] = $serviceName;
		}

		$this->setupRepositoryLoader($repositoriesMap);
		return $repositories;
	}


	protected function getRepositoryName(string $serviceName, ServiceDefinition $serviceDefinition): string
	{
		return $serviceName;
	}


	protected function setupRepositoryLoader(array $repositoriesMap)
	{
		$this->builder->addDefinition($this->extension->prefix('repositoryLoader'))
			->setClass(RepositoryLoader::class)
			->setArguments([
				'repositoryNamesMap' => $repositoriesMap,
			]);
	}
}
