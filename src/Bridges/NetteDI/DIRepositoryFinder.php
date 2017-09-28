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
	public function initRepositories(string $modelClass, ContainerBuilder $containerBuilder, callable $prefixCb): array
	{
		$types = $containerBuilder->findByType(IRepository::class);
		$repositories = [];
		$repositoriesMap = [];
		foreach ($types as $serviceName => $serviceDefinition) {
			$serviceDefinition->addSetup('setModel', [$prefixCb('@model')]);
			$class = $serviceDefinition->getClass();
			assert($class !== null);
			$name = $this->getRepositoryName($serviceName, $serviceDefinition);
			$repositories[$name] = $class;
			$repositoriesMap[$class] = $serviceName;
		}

		$this->setupRepositoryLoader($repositoriesMap, $containerBuilder, $prefixCb);
		return $repositories;
	}


	protected function getRepositoryName(string $serviceName, ServiceDefinition $serviceDefinition): string
	{
		return $serviceName;
	}


	protected function setupRepositoryLoader(array $repositoriesMap, ContainerBuilder $builder, callable $prefixCb)
	{
		$builder->addDefinition($prefixCb('repositoryLoader'))
			->setClass(RepositoryLoader::class)
			->setArguments([
				'repositoryNamesMap' => $repositoriesMap,
			]);
	}
}
