<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\Caching\Cache;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;
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

	/** @var list<Reference> */
	protected array $extensions = [];


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

		$this->extensions = $this->setupExtensions();

		$repositoryFinderClass = $this->config->repositoryFinder;
		if (!is_subclass_of($repositoryFinderClass, IRepositoryFinder::class)) {
			throw new InvalidStateException('Repository finder does not implement Nextras\Orm\Bridges\NetteDI\IRepositoryFinder interface.');
		}

		$this->repositoryFinder = new $repositoryFinderClass($this->builder, $this, $this->modelClass);
		$this->repositoryFinder->registerRepositories();

		$this->setupCache();
		$this->setupDependencyProvider();
		$this->setupMetadataParserFactory($this->extensions);
		$this->setupMetadataStorage();
		$this->setupModel($this->modelClass, $this->extensions);
		$this->setupRepositoryLoader();
		$this->initializeMetadata();
	}


	public function beforeCompile(): void
	{
		$repositories = $this->repositoryFinder->resolveRepositories();
		$this->configureRepositories($repositories, $this->extensions);
		$this->setupDbalMapperDependencies();
	}


	/**
	 * Normalizes each configured extension into a reference to a single shared
	 * service, so extensions are instantiated once and reused across the metadata
	 * factory, model and every repository setup.
	 *
	 * An extension may be given as:
	 *  - a `@name` / `@Type` string referencing an already registered service,
	 *  - a class name string, or
	 *  - a {@see Statement} factory; the latter two are registered as new services.
	 *
	 * @return list<Reference>
	 */
	protected function setupExtensions(): array
	{
		$references = [];
		foreach ($this->config->extensions as $i => $extension) {
			if (is_string($extension) && str_starts_with($extension, '@')) {
				$target = substr($extension, 1);
				$references[] = str_contains($target, '\\')
					? Reference::fromType($target)
					: new Reference($target);
				continue;
			}

			$name = $this->prefix('extension.' . $i);
			$this->builder->addDefinition($name)
				->setFactory($extension)
				->setAutowired(false);
			$references[] = new Reference($name);
		}
		return $references;
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
	 * @param list<Reference> $extensions
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


	protected function setupMetadataStorage(): void
	{
		$metadataName = $this->prefix('metadataStorage');
		if ($this->builder->hasDefinition($metadataName)) {
			return;
		}

		$this->builder->addDefinition($metadataName)
			->setType(MetadataStorage::class)
			->setArguments([
				'cache' => $this->prefix('@cache'),
				'metadataParserFactory' => $this->prefix('@metadataParserFactory'),
				'repositoryLoader' => $this->prefix('@repositoryLoader'),
			])
			->setAutowired($this->config->autowiredInternalServices);
	}


	/**
	 * @param class-string<IModel> $modelClass
	 * @param list<Reference> $extensions
	 */
	protected function setupModel(string $modelClass, array $extensions): void
	{
		$modelName = $this->prefix('model');
		if ($this->builder->hasDefinition($modelName)) {
			return;
		}

		$this->builder->addDefinition($modelName)
			->setType($modelClass)
			->setArguments([
				'repositoryLoader' => $this->prefix('@repositoryLoader'),
				'metadataStorage' => $this->prefix('@metadataStorage'),
			])
			->addSetup('foreach (? as $e) { $e->configureModel($service); }', [$extensions]);
	}


	protected function setupRepositoryLoader(): void
	{
		$this->builder->addDefinition($this->prefix('repositoryLoader'))
			->setType(DiRepositoryLoader::class);
	}


	/**
	 * @param list<DiRepositoryEntry> $repositories
	 * @param list<Reference> $extensions
	 */
	protected function configureRepositories(
		array $repositories,
		array $extensions,
	): void
	{
		$classNamesToDiNameMap = [];
		$nameToDiNameMap = [];
		$entityClassNameToRepositoryClassNameMap = [];

		foreach ($repositories as $repository) {
			$repositoryServiceName = $repository->service->getName();
			$classNamesToDiNameMap[$repository->className] = $repositoryServiceName;
			if ($repository->name !== null) {
				$nameToDiNameMap[$repository->name] = $repositoryServiceName;
			}
			$entityClassNames = $repository->className::getEntityClassNames();
			foreach ($entityClassNames as $entityClassName) {
				$entityClassNameToRepositoryClassNameMap[$entityClassName] = $repository->className;
			}

			$repository->service->addSetup('setModel', [$this->prefix('@model')]);
			$repository->service->addSetup(
				'foreach(? as $e) { $e->configureMapper($service->getMapper()); $e->configureRepository($service); }',
				[$extensions]
			);
		}

		$loader = $this->builder->getDefinition($this->prefix('repositoryLoader'));
		if ($loader instanceof ServiceDefinition && $loader->getType() === DiRepositoryLoader::class) {
			$loader->setArgument('repositoryClassNameToDiNameMap', $classNamesToDiNameMap);
			$loader->setArgument('repositoryNameToDiNameMap', $nameToDiNameMap);
			$loader->setArgument('entityClassNameToRepositoryClassNameMap', $entityClassNameToRepositoryClassNameMap);
		}

		$metadataStorage = $this->builder->getDefinition($this->prefix('metadataStorage'));
		if ($metadataStorage instanceof ServiceDefinition) {
			$metadataStorage->setArgument('entityClassesMap', $entityClassNameToRepositoryClassNameMap);
		}
	}


	protected function initializeMetadata(): void
	{
		if (!$this->config->initializeMetadata) return;
		// getMetadata() to force initialization when Nette DI's lazy proxies are enabled.
		$this->initialization->addBody('$this->getService(?)->getLoadedMetadata();', [
			$this->prefix('metadataStorage'),
		]);
	}
}
