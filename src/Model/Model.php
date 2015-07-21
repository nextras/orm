<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Model;

use Nette\Object;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\LogicException;
use Nextras\Orm\Repository\IRepository;


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
		$allPersisted = [];
		$allRemoved = [];
		foreach ($this->getLoadedRepositories() as $repository) {
			list($persisted, $removed) = $repository->processFlush();
			$allPersisted = array_merge($allPersisted, $persisted);
			$allRemoved = array_merge($allRemoved, $removed);
		}

		$this->onFlush($allPersisted, $allRemoved);
	}


	public function clearIdentityMapAndCaches($areYouSure)
	{
		if ($areYouSure !== self::I_KNOW_WHAT_I_AM_DOING) {
			throw new LogicException('Use this method only if you are sure what are you doing.');
		}

		foreach ($this->getLoadedRepositories() as $repository) {
			$repository->processClearIdentityMapAndCaches($areYouSure);
		}
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


	/** @return IRepository[] */
	private function getLoadedRepositories()
	{
		$repositories = [];
		foreach (array_keys($this->configuration[0]) as $className) {
			if ($this->loader->isCreated($className)) {
				$repositories[] = $this->loader->getRepository($className);
			}
		}

		return $repositories;
	}

}
