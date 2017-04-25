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
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use Nextras\Orm\Model\Model;
use Nextras\Orm\RuntimeException;


class PhpDocRepositoryFinder implements IRepositoryFinder
{
	/** @var ContainerBuilder */
	protected $builder;

	/** @var callable */
	protected $prefixCb;


	public function initRepositories(string $modelClass, ContainerBuilder $containerBuilder, callable $prefixCb): array
	{
		$this->builder = $containerBuilder;
		$this->prefixCb = $prefixCb;

		$repositories = $this->findRepositories($modelClass);
		$repositoriesMap = [];
		foreach ($repositories as $repositoryName => $repositoryClass) {
			$this->setupMapperService($repositoryName, $repositoryClass);
			$this->setupRepositoryService($repositoryName, $repositoryClass);
			$repositoriesMap[$repositoryClass] = $this->prefix('repositories.' . $repositoryName);
		}

		$this->setupRepositoryLoader($repositoriesMap);
		return $repositories;
	}


	protected function prefix(string $name): string
	{
		return call_user_func($this->prefixCb, $name);
	}


	protected function findRepositories(string $modelClass): array
	{
		if ($modelClass === Model::class) {
			throw new InvalidStateException('Your model has to inherit from ' . Model::class . '. Use compiler extension configuration - model key.');
		}

		$modelReflection = new \ReflectionClass($modelClass);
		$this->builder->addDependency($modelReflection->getFileName());

		$repositories = [];
		preg_match_all(
			'~^  [ \t*]*  @property(?:|-read)  [ \t]+  ([^\s$]+)  [ \t]+  \$  (\w+)  ()~mx',
			(string) $modelReflection->getDocComment(), $matches, PREG_SET_ORDER
		);

		foreach ($matches as list(, $type, $name)) {
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
		$mapperName = $this->prefix('mappers.' . $repositoryName);
		if ($this->builder->hasDefinition($mapperName)) {
			return;
		}

		$mapperClass = str_replace('Repository', 'Mapper', $repositoryClass);
		if (!class_exists($mapperClass)) {
			throw new InvalidStateException("Unknown mapper for '{$repositoryName}' repository.");
		}
		if (in_array(DbalMapper::class, class_parents($mapperClass), TRUE)) {
			$this->setupDbalMapperDependencies();
		}

		$this->builder->addDefinition($mapperName)
			->setClass($mapperClass)
			->setArguments([
				'cache' => $this->prefix('@cache'),
			]);
	}


	protected function setupRepositoryService(string $repositoryName, string $repositoryClass)
	{
		$serviceName = $this->prefix('repositories.' . $repositoryName);
		if ($this->builder->hasDefinition($serviceName)) {
			return;
		}

		$this->builder->addDefinition($serviceName)
			->setClass($repositoryClass)
			->setArguments([
				$this->prefix('@mappers.' . $repositoryName),
				$this->prefix('@dependencyProvider'),
			])
			->addSetup('setModel', [$this->prefix('@model')]);
	}


	protected function setupRepositoryLoader(array $repositoriesMap)
	{
		$this->builder->addDefinition($this->prefix('repositoryLoader'))
			->setClass(RepositoryLoader::class)
			->setArguments([
				'repositoryNamesMap' => $repositoriesMap,
			]);
	}


	protected function setupDbalMapperDependencies()
	{
		$name = $this->prefix('mapperCoordinator');
		if (!$this->builder->hasDefinition($name)) {
			$this->builder->addDefinition($name)
				->setClass(DbalMapperCoordinator::class);
		}
	}
}
