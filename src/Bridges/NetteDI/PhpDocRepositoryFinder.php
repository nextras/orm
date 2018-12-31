<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Bridges\NetteDI;

use Nette\DI\ContainerBuilder;
use Nette\Utils\Reflection;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\Model;
use Nextras\Orm\RuntimeException;


class PhpDocRepositoryFinder implements IRepositoryFinder
{
	/** @var string */
	protected $modelClass;

	/** @var ContainerBuilder */
	protected $builder;

	/** @var OrmExtension */
	protected $extension;


	public function __construct(string $modelClass, ContainerBuilder $containerBuilder, OrmExtension $extension)
	{
		$this->modelClass = $modelClass;
		$this->builder = $containerBuilder;
		$this->extension = $extension;
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


	protected function findRepositories(string $modelClass): array
	{
		if ($modelClass === Model::class) {
			throw new InvalidStateException('Your model has to inherit from ' . Model::class . '. Use compiler extension configuration - model key.');
		}

		$modelReflection = new \ReflectionClass($modelClass);
		$classFileName = $modelReflection->getFileName();
		assert($classFileName !== false);
		$this->builder->addDependency($classFileName);

		$repositories = [];
		preg_match_all(
			'~^  [ \t*]*  @property(?:|-read)  [ \t]+  ([^\s$]+)  [ \t]+  \$  (\w+)  ()~mx',
			(string) $modelReflection->getDocComment(), $matches, PREG_SET_ORDER
		);

		foreach ($matches as [, $type, $name]) {
			$type = Reflection::expandClassName($type, $modelReflection);
			if (!class_exists($type)) {
				throw new RuntimeException("Repository '{$type}' does not exist.");
			}

			$repositories[$name] = $type;
		}

		return $repositories;
	}


	protected function setupMapperService(string $repositoryName, string $repositoryClass)
	{
		$mapperName = $this->extension->prefix('mappers.' . $repositoryName);
		if ($this->builder->hasDefinition($mapperName)) {
			return;
		}

		$mapperClass = str_replace('Repository', 'Mapper', $repositoryClass);
		if (!class_exists($mapperClass)) {
			throw new InvalidStateException("Unknown mapper for '{$repositoryName}' repository.");
		}

		$this->builder->addDefinition($mapperName)
			->setClass($mapperClass)
			->setArguments([
				'cache' => $this->extension->prefix('@cache'),
			]);
	}


	protected function setupRepositoryService(string $repositoryName, string $repositoryClass)
	{
		$serviceName = $this->extension->prefix('repositories.' . $repositoryName);
		if ($this->builder->hasDefinition($serviceName)) {
			return;
		}

		$this->builder->addDefinition($serviceName)
			->setClass($repositoryClass)
			->setArguments([
				$this->extension->prefix('@mappers.' . $repositoryName),
				$this->extension->prefix('@dependencyProvider'),
			])
			->addSetup('setModel', [$this->extension->prefix('@model')]);
	}


	protected function setupRepositoryLoader(array $repositoriesMap)
	{
		$this->builder->addDefinition($this->extension->prefix('repositoryLoader'))
			->setClass(RepositoryLoader::class)
			->setArguments([
				'repositoryNamesMap' => $repositoriesMap,
			]);
	}
}
