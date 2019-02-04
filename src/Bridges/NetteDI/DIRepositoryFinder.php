<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\ContainerBuilder;
use Nextras\Orm\InvalidStateException;
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


	public function loadConfiguration(): ?array
	{
		return null;
	}


	public function beforeCompile(): ?array
	{
		$types = $this->builder->findByType(IRepository::class);
		$repositories = [];
		$repositoriesMap = [];
		foreach ($types as $serviceName => $serviceDefinition) {
			$serviceName = (string) $serviceName;
			if ($serviceDefinition instanceof \Nette\DI\Definitions\FactoryDefinition) {
				$serviceDefinition
					->getResultDefinition()
					->addSetup('setModel', [$this->extension->prefix('@model')]);
				$name = $this->getRepositoryName($serviceName, $serviceDefinition);

			} elseif ($serviceDefinition instanceof \Nette\DI\Definitions\ServiceDefinition || $serviceDefinition instanceof \Nette\DI\ServiceDefinition) {
				$serviceDefinition
					->addSetup('setModel', [$this->extension->prefix('@model')]);
				$name = $this->getRepositoryName($serviceName, $serviceDefinition);

			} else {
				$type = $serviceDefinition->getType();
				throw new InvalidStateException(
					"It seems DI defined repository of type '$type' is not defined as one of supported DI services.
					Orm can only work with ServiceDefinition or FactoryDefinition services."
				);
			}

			$class = $serviceDefinition->getType();
			assert($class !== null);
			$repositories[$name] = $class;
			$repositoriesMap[$class] = $serviceName;
		}

		$this->setupRepositoryLoader($repositoriesMap);
		return $repositories;
	}


	/**
	 * @param \Nette\DI\Definitions\FactoryDefinition|\Nette\DI\Definitions\ServiceDefinition|\Nette\DI\ServiceDefinition $serviceDefinition
	 */
	protected function getRepositoryName(string $serviceName, $serviceDefinition): string
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
