<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\ContainerBuilder;
use Nette\Utils\Reflection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\RuntimeException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use ReflectionClass;


class PhpDocRepositoryFinder implements IRepositoryFinder
{
	public function __construct(
		protected readonly string $modelClass,
		protected readonly ContainerBuilder $builder,
		protected readonly OrmExtension $extension,
	)
	{
	}


	public function loadConfiguration(): ?array
	{
		$repositories = $this->findRepositories($this->modelClass);
		$repositoriesMap = [];

		foreach ($repositories as $repositoryName => $repositoryClass) {
			$this->setupMapperService($repositoryName, $repositoryClass);
			$this->setupRepositoryService($repositoryName, $repositoryClass);
			$repositoriesMap[$repositoryClass] = $this->extension->prefix('repositories.' . $repositoryName);
		}

		$this->setupRepositoryLoader($repositoriesMap);
		return $repositories;
	}


	public function beforeCompile(): ?array
	{
		return null;
	}


	/**
	 * @param class-string<IModel> $modelClass
	 * @return array<string, class-string<IRepository<IEntity>>>
	 */
	protected function findRepositories(string $modelClass): array
	{
		if ($modelClass === Model::class) {
			throw new InvalidStateException('Your model has to inherit from ' . Model::class . '. Use compiler extension configuration - model key.');
		}

		$modelReflection = new ReflectionClass($modelClass);
		$classFileName = $modelReflection->getFileName();
		assert($classFileName !== false);
		$this->builder->addDependency($classFileName);

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
			/** @var class-string<IRepository<IEntity>> $type */
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


	protected function setupMapperService(string $repositoryName, string $repositoryClass): void
	{
		$mapperName = $this->extension->prefix('mappers.' . $repositoryName);
		if ($this->builder->hasDefinition($mapperName)) {
			return;
		}

		$mapperClass = str_replace('Repository', 'Mapper', $repositoryClass);
		if (!class_exists($mapperClass)) {
			throw new InvalidStateException("Unknown mapper for '{$repositoryName}' repository.");
		}

		/** @var \stdClass $config */
		$config = $this->extension->getConfig();
		if ($config->connection !== null) {
			$connection = ['connection' => $config->connection];
		} else {
			$connection = [];
		}

		$this->builder->addDefinition($mapperName)
			->setType($mapperClass)
			->setArguments([
				'cache' => $this->extension->prefix('@cache'),
				'mapperCoordinator' => $this->extension->prefix('@mapperCoordinator'),
			] + $connection);
	}


	protected function setupRepositoryService(string $repositoryName, string $repositoryClass): void
	{
		$serviceName = $this->extension->prefix('repositories.' . $repositoryName);
		if ($this->builder->hasDefinition($serviceName)) {
			return;
		}

		$this->builder->addDefinition($serviceName)
			->setType($repositoryClass)
			->setArguments([
				$this->extension->prefix('@mappers.' . $repositoryName),
				$this->extension->prefix('@dependencyProvider'),
			])
			->addSetup('setModel', [$this->extension->prefix('@model')]);
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
