<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\ContainerBuilder;
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
			$name = $this->getRepositoryName($class);
			$repositories[$name] = $class;
			$repositoriesMap[$class] = $serviceName;
		}

		$this->setupRepositoryLoader($repositoriesMap, $containerBuilder, $prefixCb);
		return $repositories;
	}


	protected function getRepositoryName(string $className): string
	{
		return str_ireplace(['repository', '\\'], ['', '_'], $className);
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
