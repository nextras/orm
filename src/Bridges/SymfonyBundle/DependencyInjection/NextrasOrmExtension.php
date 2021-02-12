<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\SymfonyBundle\DependencyInjection;


use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Caching\Storages\FileStorage;
use Nette\Utils\Reflection;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Bridges\SymfonyBundle\RepositoryLoader;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\RuntimeException;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\IRepositoryLoader;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use ReflectionClass;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;


class NextrasOrmExtension extends Extension
{
	/**
	 * @param array<mixed> $configs
	 */
	public function load(array $configs, ContainerBuilder $builder): void
	{
		$configuration = new Configuration();
		$config = $this->processConfiguration($configuration, $configs);

		$modelClass = $config['model'];
		$repositories = $this->findRepositories($builder, $modelClass);
		$modelConfig = Model::getConfiguration($repositories);

		foreach ($repositories as $repositoryName => $repositoryClass) {
			$mapperClass = str_replace('Repository', 'Mapper', $repositoryClass);
			$this->setupMapper($builder, $mapperClass);
			$this->setupRepository($builder, $repositoryClass, $mapperClass, $modelClass);
		}

		$this->setupCacheStorage($builder);
		$this->setupCache($builder);
		$this->setupDbalMapperCoordinator($builder);
		$this->setupRepositoryLoader($builder);
		$this->setupMetadataParserFactory($builder);
		$this->setupMetadataStorage($builder, $modelConfig[2]);
		$this->setupModel($builder, $modelClass, $modelConfig);
	}


	/**
	 * @return array<string, string>
	 * @phpstan-param class-string<\Nextras\Orm\Model\IModel> $modelClass
	 * @phpstan-return array<string, class-string<IRepository<\Nextras\Orm\Entity\IEntity>>>
	 */
	protected function findRepositories(ContainerBuilder $builder, string $modelClass): array
	{
		if ($modelClass === Model::class) {
			throw new InvalidStateException('Your model has to inherit from ' . Model::class . '. Use compiler extension configuration - model key.');
		}

		$modelReflection = new ReflectionClass($modelClass);
		$classFileName = $modelReflection->getFileName();
		assert($classFileName !== false);
		$builder->addResource(new FileResource($classFileName));

		$repositories = [];
		preg_match_all(
			'~^  [ \t*]*  @property(?:|-read)  [ \t]+  ([^\s$]+)  [ \t]+  \$  (\w+)  ()~mx',
			(string) $modelReflection->getDocComment(), $matches, PREG_SET_ORDER
		);

		/**
		 * @var string $type
		 * @var string $name
		 */
		foreach ($matches as [, $type, $name]) {
			/** @phpstan-var class-string<IRepository<\Nextras\Orm\Entity\IEntity>> $type */
			$type = Reflection::expandClassName($type, $modelReflection);
			if (!class_exists($type)) {
				throw new RuntimeException("Repository '{$type}' does not exist.");
			}

			$rc = new ReflectionClass($type);
			assert($rc->implementsInterface(IRepository::class), sprintf(
				'Property "%s" of class "%s" with type "%s" does not implement interface %s.',
				$modelClass, $name, $type, IRepository::class
			));

			$repositories[$name] = $type;
		}

		return $repositories;
	}


	private function setupCacheStorage(ContainerBuilder $builder): void
	{
		if ($builder->has(IStorage::class)) {
			return;
		}

		$definition = new Definition(FileStorage::class);
		$definition->setArgument('$dir', $builder->getParameter('kernel.cache_dir'));

		$builder->setDefinition(FileStorage::class, $definition);
		$builder->setAlias(IStorage::class, FileStorage::class);
	}


	private function setupCache(ContainerBuilder $builder): void
	{
		if ($builder->has(Cache::class)) {
			return;
		}

		$definition = new Definition(Cache::class);
		$definition->setArgument('$storage', new Reference(IStorage::class));
		$definition->setArgument('$namespace', 'nextras_orm');

		$builder->setDefinition(Cache::class, $definition);
	}


	private function setupDbalMapperCoordinator(ContainerBuilder $builder): void
	{
		if ($builder->has(DbalMapperCoordinator::class)) {
			return;
		}

		$definition = new Definition(DbalMapperCoordinator::class);
		$definition->setArgument('$connection', new Reference(IConnection::class));

		$builder->setDefinition(DbalMapperCoordinator::class, $definition);
	}


	private function setupMapper(ContainerBuilder $builder, string $mapperClass): void
	{
		if ($builder->has($mapperClass)) {
			return;
		}

		$definition = new Definition($mapperClass);
		$definition->setArgument('$connection', new Reference(IConnection::class));
		$definition->setArgument('$mapperCoordinator', new Reference(DbalMapperCoordinator::class));
		$definition->setArgument('$cache', new Reference(Cache::class));

		$builder->setDefinition($mapperClass, $definition);
	}


	private function setupRepository(ContainerBuilder $builder, string $repositoryClass, string $mapperClass, string $modelClass): void
	{
		if ($builder->has($repositoryClass)) {
			return;
		}

		$definition = new Definition($repositoryClass);
		$definition->setArgument('$mapper', new Reference($mapperClass));
		$definition->addMethodCall('setModel', [new Reference($modelClass)]);
		$definition->setPublic(true);

		$builder->setDefinition($repositoryClass, $definition);
	}


	private function setupRepositoryLoader(ContainerBuilder $builder): void
	{
		if ($builder->has(IRepositoryLoader::class)) {
			return;
		}

		$definition = new Definition(RepositoryLoader::class);
		$definition->setArgument('$container', new Reference(ContainerInterface::class));

		$builder->setDefinition(RepositoryLoader::class, $definition);
		$builder->setAlias(IRepositoryLoader::class, RepositoryLoader::class);
	}


	private function setupMetadataParserFactory(ContainerBuilder $builder): void
	{
		if ($builder->has(IMetadataParserFactory::class)) {
			return;
		}

		$definition = new Definition(MetadataParserFactory::class);

		$builder->setDefinition(MetadataParserFactory::class, $definition);
		$builder->setAlias(IMetadataParserFactory::class, MetadataParserFactory::class);
	}


	/**
	 * @param array<string, string> $entityClassMap
	 */
	private function setupMetadataStorage(ContainerBuilder $builder, array $entityClassMap): void
	{
		if ($builder->has(MetadataStorage::class)) {
			return;
		}

		$definition = new Definition(MetadataStorage::class);
		$definition->setArgument('$entityClassesMap', $entityClassMap);
		$definition->setArgument('$cache', new Reference(Cache::class));
		$definition->setArgument('$metadataParserFactory', new Reference(IMetadataParserFactory::class));
		$definition->setArgument('$repositoryLoader', new Reference(IRepositoryLoader::class));

		$builder->setDefinition(MetadataStorage::class, $definition);
	}


	/**
	 * @param mixed[] $config
	 */
	private function setupModel(ContainerBuilder $builder, string $modelClass, array $config): void
	{
		if ($builder->has($modelClass)) {
			return;
		}

		$definition = new Definition($modelClass);
		$definition->setArgument('$configuration', $config);
		$definition->setArgument('$repositoryLoader', new Reference(IRepositoryLoader::class));
		$definition->setArgument('$metadataStorage', new Reference(MetadataStorage::class));

		$builder->setDefinition($modelClass, $definition);
		$builder->setAlias(IModel::class, $modelClass);
	}
}
