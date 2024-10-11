<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Repository\IRepository;


class DIRepositoryFinder implements IRepositoryFinder
{
	private ContainerBuilder $builder;
	private OrmExtension $extension;


	// @phpstan-ignore-next-line https://github.com/phpstan/phpstan/issues/587
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
			if ($serviceDefinition instanceof FactoryDefinition) {
				$serviceDefinition
					->getResultDefinition()
					->addSetup('setModel', [$this->extension->prefix('@model')]);
				$name = $this->getRepositoryName($serviceName, $serviceDefinition);

			} elseif ($serviceDefinition instanceof ServiceDefinition || $serviceDefinition instanceof \Nette\DI\ServiceDefinition) { // @phpstan-ignore-line
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

			/** @var class-string<IRepository<IEntity>> $class */
			$class = $serviceDefinition->getType();
			$repositories[$name] = $class;
			$repositoriesMap[$class] = $serviceName;
		}

		$this->setupRepositoryLoader($repositoriesMap);
		return $repositories;
	}


	/**
	 * @param FactoryDefinition|ServiceDefinition|ServiceDefinition $serviceDefinition
	 */
	protected function getRepositoryName(string $serviceName, $serviceDefinition): string
	{
		return $serviceName;
	}


	/**
	 * @param array<class-string<IRepository<IEntity>>, string> $repositoriesMap
	 */
	protected function setupRepositoryLoader(array $repositoriesMap): void
	{
		$this->builder->addDefinition($this->extension->prefix('repositoryLoader'))
			->setType(RepositoryLoader::class)
			->setArguments([
				'repositoryNamesMap' => $repositoriesMap,
			]);
	}
}
