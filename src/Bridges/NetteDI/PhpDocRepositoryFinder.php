<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Utils\Reflection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Exception\RuntimeException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use ReflectionClass;


class PhpDocRepositoryFinder implements IRepositoryFinder
{
	/** @var list<DiRepositoryEntry> */
	private array $repositories = [];


	#[\Override]
	public function __construct(
		protected readonly ContainerBuilder $builder,
		protected readonly OrmExtension $extension,
		protected readonly string $modelClass,
	)
	{
	}


	#[\Override]
	public function registerRepositories(): void
	{
		$repositories = $this->findRepositories($this->modelClass);
		foreach ($repositories as $name => $className) {
			$service = $this->setupRepositoryService($name, $className);
			$this->repositories[] = new DiRepositoryEntry(
				className: $className,
				name: $name,
				service: $service,
			);
		}
	}


	#[\Override]
	public function resolveRepositories(): array
	{
		return $this->repositories;
	}


	/**
	 * @param class-string<IModel> $modelClass
	 * @return array<string, class-string<IRepository<*>>>
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
			'~^  [ \t*]*  @property(?:|-read)  [ \t]+  ([^\s$]+)  [ \t]+  \$  (\w+)~mx',
			(string) $modelReflection->getDocComment(), $matches, PREG_SET_ORDER
		);

		foreach ($matches as [, $type, $name]) {
			/** @var class-string<IRepository<IEntity>> $type */
			$type = Reflection::expandClassName($type, $modelReflection); // @phpstan-ignore argument.type (https://github.com/phpstan/phpstan/issues/12459#issuecomment-2607123277)
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


	/**
	 * @param class-string<IRepository<*>> $repositoryClass
	 */
	protected function setupRepositoryService(string $repositoryName, string $repositoryClass): ServiceDefinition
	{
		$serviceName = $this->extension->prefix('repositories.' . $repositoryName);
		if ($this->builder->hasDefinition($serviceName)) {
			$service = $this->builder->getDefinition($serviceName);
			return match (true) {
				$service instanceof ServiceDefinition => $service,
				$service instanceof FactoryDefinition => $service->getResultDefinition(),
				default => throw new NotSupportedException("Service " . $service::class . " type is not supported by Nextras Orm.")
			};
		}

		$this->setupMapperService($repositoryName, $repositoryClass);
		return $this->builder->addDefinition($serviceName)
			->setType($repositoryClass)
			->setArguments([
				$this->extension->prefix('@mappers.' . $repositoryName),
				$this->extension->prefix('@dependencyProvider'),
			]);
	}


	protected function setupMapperService(string $repositoryName, string $repositoryClass): void
	{
		$mapperName = $this->extension->prefix('mappers.' . $repositoryName);
		if ($this->builder->hasDefinition($mapperName)) {
			return;
		}

		$mapperClass = str_replace('Repository', 'Mapper', $repositoryClass);
		if (!class_exists($mapperClass)) {
			throw new InvalidStateException("Unknown $mapperClass mapper for '{$repositoryName}' repository.");
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
}
