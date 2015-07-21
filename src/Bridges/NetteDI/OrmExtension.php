<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\ContainerBuilder;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator;
use Nette\Reflection\AnnotationsParser;
use Nette\Reflection\ClassType;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\Model;
use Nextras\Orm\RuntimeException;


class OrmExtension extends CompilerExtension
{

	public function loadConfiguration()
	{
		$config = $this->getConfig();
		if (!isset($config['model'])) {
			throw new InvalidStateException('Model is not defined.');
		}

		$repositories = $this->getRepositoryList($config['model']);
		$repositoriesConfig = Model::getConfiguration($repositories);

		$this->setupDependencyProvider();
		$this->setupRepositoryLoader($repositories);
		$this->setupMetadataStorage($repositoriesConfig);
		$this->setupRepositoriesAndMappers($repositories);
		$this->setupModel($config['model'], $repositoriesConfig);
	}


	protected function getRepositoryList($modelClass)
	{
		$modelReflection = new ClassType($modelClass);

		$builder = $this->getContainerBuilder();
		$builder->addDependency($modelReflection->getFileName());

		$repositories = [];
		foreach ($modelReflection->getAnnotations() as $key => $annotations) {
			if ($key !== 'property-read') {
				continue;
			}

			foreach ($annotations as $annotation) {
				list($class, $name) = preg_split('#\s+#', $annotation);
				$class = AnnotationsParser::expandClassName($class, $modelReflection);
				if (!class_exists($class)) {
					throw new RuntimeException("Repository '{$class}' does not exist.");
				}

				$repositories[ltrim($name, '$')] = $class;
			}
		}

		return $repositories;
	}


	protected function setupDependencyProvider()
	{
		$builder = $this->getContainerBuilder();
		$providerName = $this->prefix('dependencyProvider');
		if (!$builder->hasDefinition($providerName)) {
			$builder->addDefinition($providerName)
				->setClass('Nextras\Orm\Bridges\NetteDI\DependencyProvider');
		}
	}


	protected function setupRepositoryLoader(array $repositories)
	{
		$map = [];
		foreach ($repositories as $name => $className) {
			$map[$className] = $this->prefix('repositories.' . $name);
		}

		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('repositoryLoader'))
			->setClass('Nextras\Orm\Bridges\NetteDI\RepositoryLoader')
			->setArguments([
				'repositoryNamesMap' => $map,
			]);
	}


	protected function setupMetadataStorage(array $repositoryConfig)
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('metadataStorage'))
			->setClass('Nextras\Orm\Model\MetadataStorage')
			->setArguments([
				'entityClassesMap' => $repositoryConfig[2],
				'repositoryLoader' => '@' . $this->prefix('repositoryLoader'),
			]);
	}


	protected function setupRepositoriesAndMappers($repositories)
	{
		$builder = $this->getContainerBuilder();

		foreach ($repositories as $repositoryName => $repositoryClass) {
			$mapperName = $this->createMapperService($repositoryName, $repositoryClass, $builder);
			$this->createRepositoryService($repositoryName, $repositoryClass, $builder, $mapperName);
		}
	}


	protected function createMapperService($repositoryName, $repositoryClass, ContainerBuilder $builder)
	{
		$mapperName = $this->prefix('mappers.' . $repositoryName);
		if (!$builder->hasDefinition($mapperName)) {
			$mapperClass = str_replace('Repository', 'Mapper', $repositoryClass);
			if (!class_exists($mapperClass)) {
				throw new InvalidStateException("Uknown mapper for '{$repositoryName}' repository.");
			}

			$builder->addDefinition($mapperName)
				->setClass($mapperClass);
		}

		return $mapperName;
	}


	protected function createRepositoryService($repositoryName, $repositoryClass, ContainerBuilder $builder, $mapperName)
	{
		$repositoryName = $this->prefix('repositories.' . $repositoryName);
		if (!$builder->hasDefinition($repositoryName)) {
			$builder->addDefinition($repositoryName)
				->setClass($repositoryClass)
				->setArguments([
					'@' . $mapperName,
					'@' . $this->prefix('dependencyProvider'),
				])
				->addSetup('setModel', ['@' . $this->prefix('model')]);
		}
	}


	protected function setupModel($modelClass, $repositoriesConfig)
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('model'))
			->setClass($modelClass)
			->setArguments([
				'configuration' => $repositoriesConfig,
				'repositoryLoader' => '@' . $this->prefix('repositoryLoader'),
				'metadataStorage' => '@' . $this->prefix('metadataStorage'),
			]);
	}

}
