<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\Caching\Cache;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;


class OrmExtension extends CompilerExtension
{
	/** @var ContainerBuilder */
	protected $builder;


	public function beforeCompile()
	{
		$configDefaults = [
			'model' => Model::class,
			'repositoryFinder' => PhpDocRepositoryFinder::class,
		];

		$config = $this->validateConfig($configDefaults);
		$repositoryFinderClass = $config['repositoryFinder'];
		$repositoryFinder = new $repositoryFinderClass();
		if (!$repositoryFinder instanceof IRepositoryFinder) {
			throw new InvalidStateException('Repository finder does not implement Nextras\Orm\Bridges\NetteDI\IRepositoryFinder interface.');
		}

		$this->builder = $this->getContainerBuilder();
		$repositories = $repositoryFinder->initRepositories($config['model'], $this->builder, function ($name) {
			return $this->prefix($name);
		});

		$repositoriesConfig = Model::getConfiguration($repositories);

		$this->setupCache();
		$this->setupDependencyProvider();
		$this->setupDbalMapperDependencies();
		$this->setupMetadataParserFactory();
		$this->setupMetadataStorage($repositoriesConfig[2]);
		$this->setupModel($config['model'], $repositoriesConfig);
	}


	protected function setupCache()
	{
		$cacheName = $this->prefix('cache');
		if ($this->builder->hasDefinition($cacheName)) {
			return;
		}

		$this->builder->addDefinition($cacheName)
			->setClass(Cache::class)
			->setArguments([
				'namespace' => $this->name,
			])
			->setAutowired(false);
	}


	protected function setupDependencyProvider()
	{
		$providerName = $this->prefix('dependencyProvider');
		if ($this->builder->hasDefinition($providerName)) {
			return;
		}

		$this->builder->addDefinition($providerName)
			->setClass(DependencyProvider::class);
	}


	protected function setupDbalMapperDependencies()
	{
		if (!$this->builder->findByType(IConnection::class)) {
			return;
		}

		$name = $this->prefix('mapperCoordinator');
		if (!$this->builder->hasDefinition($name)) {
			$this->builder->addDefinition($name)
				->setClass(DbalMapperCoordinator::class);
		}
	}


	protected function setupMetadataParserFactory()
	{
		$factoryName = $this->prefix('metadataParserFactory');
		if ($this->builder->hasDefinition($factoryName)) {
			return;
		}

		$this->builder->addDefinition($factoryName)
			->setClass(MetadataParserFactory::class);
	}


	protected function setupMetadataStorage(array $entityClassMap)
	{
		$metadataName = $this->prefix('metadataStorage');
		if ($this->builder->hasDefinition($metadataName)) {
			return;
		}

		$this->builder->addDefinition($metadataName)
			->setClass(MetadataStorage::class)
			->setArguments([
				'entityClassesMap' => $entityClassMap,
				'cache' => $this->prefix('@cache'),
				'metadataParserFactory' => $this->prefix('@metadataParserFactory'),
				'repositoryLoader' => $this->prefix('@repositoryLoader'),
			]);
	}


	protected function setupModel(string $modelClass, array $repositoriesConfig)
	{
		$modelName = $this->prefix('model');
		if ($this->builder->hasDefinition($modelName)) {
			return;
		}

		$this->builder->addDefinition($modelName)
			->setClass($modelClass)
			->setArguments([
				'configuration' => $repositoriesConfig,
				'repositoryLoader' => $this->prefix('@repositoryLoader'),
				'metadataStorage' => $this->prefix('@metadataStorage'),
			]);
	}
}
