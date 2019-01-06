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

	/** @var IRepositoryFinder */
	protected $repositoryFinder;

	/** @var string */
	protected $modelClass;


	public function loadConfiguration()
	{
		$this->builder = $this->getContainerBuilder();

		$configDefaults = [
			'model' => Model::class,
			'repositoryFinder' => PhpDocRepositoryFinder::class,
		];
		$config = $this->validateConfig($configDefaults);
		$this->modelClass = $config['model'];

		$repositoryFinderClass = $config['repositoryFinder'];
		$this->repositoryFinder = new $repositoryFinderClass($this->modelClass, $this->builder, $this);
		if (!$this->repositoryFinder instanceof IRepositoryFinder) {
			throw new InvalidStateException('Repository finder does not implement Nextras\Orm\Bridges\NetteDI\IRepositoryFinder interface.');
		}

		$repositories = $this->repositoryFinder->loadConfiguration();

		$this->setupCache();
		$this->setupDependencyProvider();
		$this->setupDbalMapperDependencies();
		$this->setupMetadataParserFactory();

		if ($repositories !== null) {
			$repositoriesConfig = Model::getConfiguration($repositories);
			$this->setupMetadataStorage($repositoriesConfig[2]);
			$this->setupModel($this->modelClass, $repositoriesConfig);
		}
	}


	public function beforeCompile()
	{
		$repositories = $this->repositoryFinder->beforeCompile();

		if ($repositories !== null) {
			$repositoriesConfig = Model::getConfiguration($repositories);
			$this->setupMetadataStorage($repositoriesConfig[2]);
			$this->setupModel($this->modelClass, $repositoriesConfig);
		}

		$this->setupDbalMapperDependencies();
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
			->setType(DependencyProvider::class);
	}


	protected function setupDbalMapperDependencies()
	{
		if (!$this->builder->findByType(IConnection::class)) {
			return;
		}

		$name = $this->prefix('mapperCoordinator');
		if (!$this->builder->hasDefinition($name)) {
			$this->builder->addDefinition($name)
				->setType(DbalMapperCoordinator::class);
		}
	}


	protected function setupMetadataParserFactory()
	{
		$factoryName = $this->prefix('metadataParserFactory');
		if ($this->builder->hasDefinition($factoryName)) {
			return;
		}

		$this->builder->addDefinition($factoryName)
			->setType(MetadataParserFactory::class);
	}


	protected function setupMetadataStorage(array $entityClassMap)
	{
		$metadataName = $this->prefix('metadataStorage');
		if ($this->builder->hasDefinition($metadataName)) {
			return;
		}

		$this->builder->addDefinition($metadataName)
			->setType(MetadataStorage::class)
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
			->setType($modelClass)
			->setArguments([
				'configuration' => $repositoriesConfig,
				'repositoryLoader' => $this->prefix('@repositoryLoader'),
				'metadataStorage' => $this->prefix('@metadataStorage'),
			]);
	}
}
