<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\Caching\Cache;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
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
	/** @var ContainerBuilder */
	protected $builder;

	/** @var IRepositoryFinder */
	protected $repositoryFinder;

	/** @var string */
	protected $modelClass;


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'model' => Expect::string()->default(Model::class),
			'repositoryFinder' => Expect::string()->default(PhpDocRepositoryFinder::class),
			'initializeMetadata' => Expect::bool()->default(false),
		]);
	}


	public function loadConfiguration(): void
	{
		$this->builder = $this->getContainerBuilder();

		$this->modelClass = $this->config->model;

		$repositoryFinderClass = $this->config->repositoryFinder;
		if (!is_subclass_of($repositoryFinderClass, IRepositoryFinder::class)) {
			throw new InvalidStateException('Repository finder does not implement Nextras\Orm\Bridges\NetteDI\IRepositoryFinder interface.');
		}
		$this->repositoryFinder = new $repositoryFinderClass($this->modelClass, $this->builder, $this);

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

		$this->initializeMetadata($this->config->initializeMetadata);
	}


	public function beforeCompile(): void
	{
		$repositories = $this->repositoryFinder->beforeCompile();

		if ($repositories !== null) {
			$repositoriesConfig = Model::getConfiguration($repositories);
			$this->setupMetadataStorage($repositoriesConfig[2]);
			$this->setupModel($this->modelClass, $repositoriesConfig);
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
			->setType(DependencyProvider::class);
	}


	protected function setupDbalMapperDependencies(): void
	{
		if (count($this->builder->findByType(IConnection::class)) === 0) {
			return;
		}

		$name = $this->prefix('mapperCoordinator');
		if (!$this->builder->hasDefinition($name)) {
			$this->builder->addDefinition($name)
				->setType(DbalMapperCoordinator::class);
		}
	}


	protected function setupMetadataParserFactory(): void
	{
		$factoryName = $this->prefix('metadataParserFactory');
		if ($this->builder->hasDefinition($factoryName)) {
			return;
		}

		$this->builder->addFactoryDefinition($factoryName)
			->setImplement(IMetadataParserFactory::class)
			->getResultDefinition()
			->setType(MetadataParser::class)
			->setArguments(['$entityClassesMap']);
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
			]);
	}


	/**
	 * @param array{
	 *     array<class-string<IRepository<IEntity>>, true>,
	 *     array<string, class-string<IRepository<IEntity>>>,
	 *     array<class-string<IEntity>, class-string<IRepository<IEntity>>>
	 *     } $repositoriesConfig
	 */
	protected function setupModel(string $modelClass, array $repositoriesConfig): void
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


	protected function initializeMetadata(bool $init): void
	{
		if (!$init) {
			return;
		}

		$this->initialization->addBody('$this->getService(?);', [
			$this->prefix('metadataStorage'),
		]);
	}
}
