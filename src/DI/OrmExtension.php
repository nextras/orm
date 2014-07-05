<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\DI;

use Nette\DI\ContainerBuilder;
use Nette\PhpGenerator;
use Nette\DI\CompilerExtension;
use Nette\Reflection\AnnotationsParser;
use Nette\Reflection\ClassType;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\RuntimeException;


class OrmExtension extends CompilerExtension
{

	public function loadConfiguration()
	{
		$config = $this->getConfig();
		if (!isset($config['model'])) {
			throw new InvalidStateException('Model is not defined.');
		}
	}


	public function beforeCompile()
	{
		$config = $this->getConfig();
		$repositories = $this->getRepositoryList($config['model']);

		$this->setupDependencyProvider();
		$this->setupRepositoriesAndMappers($repositories);
		$this->setupModel($config['model'], $repositories);
	}


	protected function setupRepositoriesAndMappers($repositories)
	{
		$builder = $this->getContainerBuilder();

		foreach ($repositories as $repositoryData) {
			$mapperName = $this->createMapperService($repositoryData, $builder);
			$this->createRepositoryService($repositoryData, $builder, $mapperName);
		}
	}


	protected function setupModel($modelClass, $repositories)
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('model'))
			->setClass($modelClass)
			->setArguments(['repositories' => $repositories]);
	}


	protected function getRepositoryList($modelClass)
	{
		$modelReflection = new ClassType($modelClass);

		$builder = $this->getContainerBuilder();
		$builder->addDependency($modelReflection->getFileName());

		$repositories = [];
		foreach ($modelReflection->getAnnotations() as $key => $annotations) {
			if ($key !== 'property-read') {
				continue;
			}

			foreach ($annotations as $annotation) {
				list($class, $name) = preg_split('#\s+#', $annotation);
				$class = AnnotationsParser::expandClassName($class, $modelReflection);
				if (!class_exists($class)) {
					throw new RuntimeException("Class repository '{$class}' does not exist.");
				}

				$repositories[] = [
					'name' => ltrim($name, '$'),
					'class' => $class,
					'entities' => call_user_func([$class, 'getEntityClassNames']),
				];
			}
		}

		return $repositories;
	}


	protected function createMapperService($repositoryData, ContainerBuilder $builder)
	{
		$mapperName = $this->prefix('mappers.' . $repositoryData['name']);
		if (!$builder->hasDefinition($mapperName)) {
			$mapperClass = substr($repositoryData['class'], 0, -10) . 'Mapper';
			if (!class_exists($mapperClass)) {
				throw new InvalidStateException("Uknown mapper for '{$repositoryData['name']}' repository.");
			}

			$builder->addDefinition($mapperName)
				->setClass($mapperClass);
		}

		return $mapperName;
	}


	protected function createRepositoryService($repositoryData, ContainerBuilder $builder, $mapperName)
	{
		$repositoryName = $this->prefix('repositories.' . $repositoryData['name']);
		if (!$builder->hasDefinition($repositoryName)) {
			$builder->addDefinition($repositoryName)
				->setClass($repositoryData['class'])
				->setArguments(['@' . $mapperName])
				->addSetup('onModelAttach', ['@' . $this->prefix('model')]);
		}
	}


	private function setupDependencyProvider()
	{
		$builder = $this->getContainerBuilder();
		$providerName = $this->prefix('dependencyProvider');
		if (!$builder->hasDefinition($providerName)) {
			$builder->addDefinition($providerName)
				->setClass('Nextras\Orm\DI\EntityDependencyProvider');
		}
	}

}
