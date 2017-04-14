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
			$name = lcfirst(str_ireplace('repository', '', substr($class, strrpos($class, '\\') + 1 ?: 0)));
			$repositories[$name] = $class;
			$repositoriesMap[$class] = $serviceName;
		}

		$this->setupRepositoryLoader($repositoriesMap, $containerBuilder, $prefixCb);
		return $repositories;
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
