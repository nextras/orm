<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Model;

use Nette\Object;
use Nextras\Orm\InvalidArgumentException;


class Model extends Object implements IModel
{
	/** @var array of callbacks with (IEntity[] $persisted, IEntity $removed) arguments */
	public $onFlush = [];

	/** @var IRepositoryLoader */
	private $loader;

	/** @var MetadataStorage */
	private $metadataStorage;

	/** @var array */
	private $configuration;


	/**
	 * Creates repository list configuration.
	 * @param  IRepository[] $repositories
	 * @return array
	 */
	public static function getConfiguration(array $repositories)
	{
		$config = [];
		foreach ($repositories as $name => $repository) {
			$className = is_object($repository) ? get_class($repository) : $repository;
			$config[0][$className] = TRUE;
			$config[1][$name] = $className;
			foreach ($repository::getEntityClassNames() as $entityClassName) {
				$config[2][$entityClassName] = $className;
			}
		}
		return $config;
	}


	public function __construct(array $configuration, IRepositoryLoader $repositoryLoader, MetadataStorage $metadataStorage)
	{
		$this->loader = $repositoryLoader;
		$this->metadataStorage = $metadataStorage;
		$this->configuration = $configuration;
	}


	public function hasRepositoryByName($name)
	{
		return isset($this->configuration[1][$name]);
	}


	public function getRepositoryByName($name)
	{
		if (!isset($this->configuration[1][$name])) {
			throw new InvalidArgumentException("Repository '$name' does not exist.'");
		}
		return $this->getRepository($this->configuration[1][$name]);
	}


	public function hasRepository($className)
	{
		return isset($this->configuration[0][$className]);
	}


	public function getRepository($className)
	{
		if (!isset($this->configuration[0][$className])) {
			throw new InvalidArgumentException("Repository '$className' does not exist.");
		}
		return $this->loader->getRepository($className);
	}


	public function getRepositoryForEntity($entity)
	{
		$entityClassName = is_string($entity) ? $entity : get_class($entity);
		if (!isset($this->configuration[2][$entityClassName])) {
			throw new InvalidArgumentException("Repository for '$entityClassName' entity does not exist.");
		}
		return $this->getRepository($this->configuration[2][$entityClassName]);
	}


	public function getMetadataStorage()
	{
		return $this->metadataStorage;
	}


	public function flush()
	{
		$repositories = [];
		foreach (array_keys($this->configuration[0]) as $className) {
			if ($this->loader->isCreated($className)) {
				$repositories[] = $this->loader->getRepository($className);
			}
		}

		$allPersisted = [];
		$allRemoved = [];
		foreach ($repositories as $repository) {
			list($persisted, $removed) = $repository->processFlush();
			$allPersisted = array_merge($allPersisted, $persisted);
			$allRemoved = array_merge($allRemoved, $removed);
		}

		$this->onFlush($allPersisted, $allRemoved);
	}


	/**
	 * Returns repository by name.
	 * @param  string   $name
	 * @return IRepository
	 */
	public function & __get($name)
	{
		$repository = $this->getRepositoryByName($name);
		return $repository;
	}

}
