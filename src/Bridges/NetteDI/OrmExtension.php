<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\Caching\Cache;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Extension;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use stdClass;
use function is_subclass_of;


/**
 * @property-read stdClass $config
 */
class OrmExtension extends CompilerExtension
{
	protected ContainerBuilder $builder;

	protected IRepositoryFinder $repositoryFinder;

	/** @var class-string<IModel> */
	protected string $modelClass;


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'model' => Expect::string()->default(Model::class),
			'extensions' => Expect::arrayOf('string|Nette\DI\Definitions\Statement')->default([]),
			'repositoryFinder' => Expect::string()->default(PhpDocRepositoryFinder::class),
			'initializeMetadata' => Expect::bool()->default(false),
			'autowiredInternalServices' => Expect::bool()->default(true),
			'connection' => Expect::string(),
		]);
	}


	public function loadConfiguration(): void
	{
		$this->builder = $this->getContainerBuilder();
		$this->modelClass = $this->config->model;

		$extensions = [];
		foreach ($this->config->extensions as $extension) {
			$extensions[] = is_string($extension) ? new Statement($extension) : $extension;
		}

		$repositoryFinderClass = $this->config->repositoryFinder;
		if (!is_subclass_of($repositoryFinderClass, IRepositoryFinder::class)) {
			throw new InvalidStateException('Repository finder does not implement Nextras\Orm\Bridges\NetteDI\IRepositoryFinder interface.');
		}
		$this->repositoryFinder = new $repositoryFinderClass($this->modelClass, $extensions, $this->builder, $this);

		$repositories = $this->repositoryFinder->loadConfiguration();

		$this->setupCache();
		$this->setupDependencyProvider();
		$this->setupDbalMapperDependencies();
		$this->setupMetadataParserFactory($extensions);

		if ($repositories !== null) {
			$repositoriesConfig = Model::getConfiguration($repositories);
			$this->setupMetadataStorage($repositoriesConfig[2]);
			$this->setupModel($this->modelClass, $repositoriesConfig, $extensions);
		}

		$this->initializeMetadata($this->config->initializeMetadata);
	}


	public function beforeCompile(): void
	{
		$repositories = $this->repositoryFinder->beforeCompile();

		if ($repositories !== null) {
			$extensions = [];
			foreach ($this->config->extensions as $extension) {
				$extensions[] = is_string($extension) ? new Statement($extension) : $extension;
			}

			$repositoriesConfig = Model::getConfiguration($repositories);
			$this->setupMetadataStorage($repositoriesConfig[2]);
			$this->setupModel($this->modelClass, $repositoriesConfig, $extensions);
		}

		$this->setupDbalMapperDependencies();
	}


	protected function setupCache(): void
	{
		$cacheName = $this->prefix('cache');
		if ($this->builder->hasDefinition($cacheName)) {
			return;
		}

		$this->builder->addDefinition($cacheName)
			->setType(Cache::class)
			->setArguments([
				'namespace' => $this->name,
			])
			->setAutowired(false);
	}


	protected function setupDependencyProvider(): void
	{
		$providerName = $this->prefix('dependencyProvider');
		if ($this->builder->hasDefinition($providerName)) {
			return;
		}

		$this->builder->addDefinition($providerName)
			->setType(DependencyProvider::class)
			->setAutowired($this->config->autowiredInternalServices);
	}


	protected function setupDbalMapperDependencies(): void
	{
		if (count($this->builder->findByType(IConnection::class)) === 0) {
			return;
		}

		$name = $this->prefix('mapperCoordinator');
		if ($this->builder->hasDefinition($name)) {
			return;
		}

		$this->builder->addDefinition($name)
			->setType(DbalMapperCoordinator::class)
			->setArguments(
				$this->config->connection !== null ? ['connection' => $this->config->connection] : [],
			)
			->setAutowired($this->config->autowiredInternalServices);
	}


	/**
	 * @param list<Extension> $extensions
	 */
	protected function setupMetadataParserFactory(array $extensions): void
	{
		$factoryName = $this->prefix('metadataParserFactory');
		if ($this->builder->hasDefinition($factoryName)) {
			return;
		}

		$this->builder->addDefinition($factoryName)
			->setType(MetadataParserFactory::class)
			->setArgument('extensions', $extensions)
			->setAutowired($this->config->autowiredInternalServices);
	}


	/**
	 * @param array<class-string<IEntity>, class-string<IRepository<IEntity>>> $entityClassMap
	 */
	protected function setupMetadataStorage(array $entityClassMap): void
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
			])
			->setAutowired($this->config->autowiredInternalServices);
	}


	/**
	 * @param array{
	 *     array<class-string<IRepository<IEntity>>, true>,
	 *     array<string, class-string<IRepository<IEntity>>>,
	 *     array<class-string<IEntity>, class-string<IRepository<IEntity>>>
	 *     } $repositoriesConfig
	 * @param list<Statement> $extensions
	 */
	protected function setupModel(string $modelClass, array $repositoriesConfig, array $extensions): void
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
			])
			->addSetup('foreach (? as $e) { $e->configureModel($service); }', [$extensions]);
	}


	protected function initializeMetadata(bool $init): void
	{
		if (!$init) {
			return;
		}

		// getMetadata() to force initialization when Nette DI's lazy proxies are enabled.
		$this->initialization->addBody('$this->getService(?)->getLoadedMetadata();', [
			$this->prefix('metadataStorage'),
		]);
	}
}
