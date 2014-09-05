<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Model;

use Nette\Caching\IStorage;
use Nette\Object;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\InvalidArgumentException;


/**
 * Static model for test ans standalone usage.
 */
class StaticModel extends Object implements IModel
{
	/** @var array */
	private $list = [];

	/** @var array */
	private $aliases = [];

	/** @var array */
	private $entities = [];

	/** @var MetadataStorage */
	private $metadataStorage;


	/**
	 * @param IRepository[] $repositories
	 * @param IStorage      $cacheStorage
	 */
	public function __construct(array $repositories, IStorage $cacheStorage)
	{
		foreach ($repositories as $name => $repository) {
			$this->addRepository($name, $repository);
		}

		$this->metadataStorage = new MetadataStorage($cacheStorage, array_keys($this->entities), $this);

		foreach ($repositories as $repository) {
			$repository->onModelAttach($this);
		}
	}


	public function getMetadataStorage()
	{
		return $this->metadataStorage;
	}


	public function hasRepository($className)
	{
		return isset($this->list[$className]);
	}


	public function getRepository($className)
	{
		if (!isset($this->list[$className])) {
			throw new InvalidArgumentException("Repository '$className' does not exist.");
		}
		return $this->list[$className];
	}


	public function hasRepositoryByName($name)
	{
		return isset($this->aliases[$name]);
	}


	public function getRepositoryByName($name)
	{
		if (!isset($this->aliases[$name])) {
			throw new InvalidArgumentException("Repository with '$name' name does not exist.");
		}
		return $this->getRepository($this->aliases[$name]);
	}


	public function getRepositoryForEntity($entity)
	{
		$class = is_string($entity) ? $entity : get_class($entity);
		if (!isset($this->entities[$class])) {
			throw new InvalidArgumentException("Unknown repository for '$class' entity.");
		}
		return $this->getRepository($this->entities[$class]);
	}


	public function flush()
	{
		$mappers = [];
		foreach ($this->repositories as $repository) {
			$mappers[] = $repository->getMapper();
		}

		foreach ($mappers as $mapper) {
			$mapper->flush();
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


	private function addRepository($name, IRepository $repository)
	{
		$class = get_class($repository);
		$this->list[$class] = $repository;
		$this->aliases[$name] = $class;
		foreach ($repository::getEntityClassNames() as $entityClass) {
			$this->entities[$entityClass] = $class;
		}

		return $this;
	}

}
