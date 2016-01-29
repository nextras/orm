<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\Caching\Cache;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\PhpGenerator;
use Nette\Reflection\AnnotationsParser;
use Nette\Reflection\ClassType;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;
use Nextras\Orm\RuntimeException;


class OrmExtension extends CompilerExtension
{
	public function loadConfiguration()
	{
		$configDefaults = [
			'metadataParserFactory' => MetadataParserFactory::class,
		];

		$config = $this->getConfig($configDefaults);
		if (!isset($config['model'])) {
			throw new InvalidStateException('Model is not defined.');
		}

		$repositories = $this->getRepositoryList($config['model']);
		$repositoriesConfig = Model::getConfiguration($repositories);

		$this->setupCache();
		$this->setupDependencyProvider();
		$this->setupMetadataParserFactory($config['metadataParserFactory']);
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


	protected function setupCache()
	{
		$builder = $this->getContainerBuilder();
		$providerName = $this->prefix('cache');
		if (!$builder->hasDefinition($providerName)) {
			$builder->addDefinition($providerName)
				->setClass(Cache::class)
				->setArguments([
					'namespace' => $this->name,
				])
				->setAutowired(false);
		}
	}


	protected function setupDependencyProvider()
	{
		$builder = $this->getContainerBuilder();
		$providerName = $this->prefix('dependencyProvider');
		if (!$builder->hasDefinition($providerName)) {
			$builder->addDefinition($providerName)
				->setClass(DependencyProvider::class);
		}
	}


	protected function setupMetadataParserFactory($class)
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('metadataParserFactory'))
			->setClass($class);
	}


	protected function setupRepositoryLoader(array $repositories)
	{
		$map = [];
		foreach ($repositories as $name => $className) {
			$map[$className] = $this->prefix('repositories.' . $name);
		}

		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('repositoryLoader'))
			->setClass(RepositoryLoader::class)
			->setArguments([
				'repositoryNamesMap' => $map,
			]);
	}


	protected function setupMetadataStorage(array $repositoryConfig)
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('metadataStorage'))
			->setClass(MetadataStorage::class)
			->setArguments([
				'entityClassesMap' => $repositoryConfig[2],
				'cache' => '@' . $this->prefix('cache'),
				'metadataParserFactory' => '@' . $this->prefix('metadataParserFactory'),
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
				throw new InvalidStateException("Unknown mapper for '{$repositoryName}' repository.");
			}

			$builder->addDefinition($mapperName)
				->setClass($mapperClass)
				->setArguments([
					'cache' => '@' . $this->prefix('cache'),
				]);
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
