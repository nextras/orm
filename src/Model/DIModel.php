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
use Nette\DI\Container;
use Nette\Object;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Repository\IRepository;


/**
 * Abstract model with support for Nette Frameowk DI container.
 */
abstract class DIModel extends Object implements IModel
{
	/** @var Container */
	protected $container;

	/** @var array */
	protected $repositories;

	/** @var MetadataStorage */
	protected $metadataStorage;

	/** @var IStorage */
	protected $cacheStorage;


	public function __construct(Container $container, IStorage $cacheStorage, array $repositories)
	{
		$this->container = $container;
		$this->cacheStorage = $cacheStorage;
		$this->repositories = [
			'class' => [],
			'names' => [],
			'entity' => [],
		];

		foreach ($repositories as $repository) {
			$this->repositories['class'][strtolower($repository['class'])] = strtolower($repository['name']);
			$this->repositories['names'][strtolower($repository['name'])] = $repository['serviceName'];
			foreach ($repository['entities'] as $entityClass) {
				$this->repositories['entity'][$entityClass] = $repository['name'];
			}
		}
	}


	public function getMetadataStorage()
	{
		if (!$this->metadataStorage) {
			$this->metadataStorage = new MetadataStorage($this->cacheStorage, array_keys($this->repositories['entity']), $this);
		}

		return $this->metadataStorage;
	}


	public function hasRepository($className)
	{
		return isset($this->repositories['class'][strtolower($className)]);
	}


	public function getRepository($className)
	{
		if (!isset($this->repositories['class'][strtolower($className)])) {
			throw new InvalidArgumentException("Repository '$className' does not exist.");
		}

		return $this->getRepositoryByName($this->repositories['class'][strtolower($className)]);
	}


	public function hasRepositoryByName($name)
	{
		return isset($this->repositories['names'][$name]);
	}


	public function getRepositoryByName($name)
	{
		if (!isset($this->repositories['names'][strtolower($name)])) {
			throw new InvalidArgumentException("Repository with '$name' name does not exist.");
		}

		return $this->container->getService($this->repositories['names'][strtolower($name)]);
	}


	public function getRepositoryForEntity($entity)
	{
		$class = is_string($entity) ? $entity : get_class($entity);
		if (!isset($this->repositories['entity'][$class])) {
			throw new InvalidArgumentException("Unknown repository for '$class' entity.");
		}

		return $this->getRepositoryByName($this->repositories['entity'][$class]);
	}


	/**
	 * Returns repository by name.
	 * @param  string   $name
	 * @return IRepository
	 */
	public function & __get($name)
	{
		$var = $this->getRepositoryByName($name);
		return $var;
	}

}
