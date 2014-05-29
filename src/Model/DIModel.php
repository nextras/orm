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
use Nextras\Orm\Entity\IEntity;
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
	protected $repositories = [];

	/** @var MetadataStorage */
	protected $metadataStorage;


	public function __construct(Container $container, IStorage $cacheStorage, array $repositories)
	{
		$this->container = $container;
		foreach ($repositories as $repository) {
			$this->repositories['class'][strtolower($repository['class'])] = FALSE;
			$this->repositories['names'][$repository['name']] = strtolower($repository['class']);
			foreach ($repository['entities'] as $entityClass) {
				$this->repositories['entity'][$entityClass] = $repository['class'];
			}
		}

		$this->metadataStorage = new MetadataStorage($cacheStorage, array_keys($this->repositories['entity']), $this);
	}


	public function getMetadataStorage()
	{
		return $this->metadataStorage;
	}


	public function hasRepository($className)
	{
		return isset($this->repositories['class'][strtolower($className)]);
	}


	public function getRepository($className)
	{
		$repository = & $this->repositories['class'][strtolower($className)];
		if ($repository === NULL) {
			throw new InvalidArgumentException("Repository '$className' does not exist.");

		} elseif ($repository === FALSE) {
			$repository = $this->container->getByType($className);
		}

		return $repository;
	}


	public function hasRepositoryByName($name)
	{
		return isset($this->repositories['names'][$name]);
	}


	public function getRepositoryByName($name)
	{
		if (!isset($this->repositories['names'][$name])) {
			throw new InvalidArgumentException("Repository with '$name' name does not exist.");
		}

		return $this->getRepository($this->repositories['names'][$name]);
	}


	public function getRepositoryForEntity(IEntity $entity)
	{
		$class = get_class($entity);
		if (!isset($this->repositories['entity'][$class])) {
			throw new InvalidArgumentException("Unknown repository for '$class' entity.");
		}

		return $this->getRepository($this->repositories['entity'][$class]);
	}


	/**
	 * Returns repository by name.
	 * @param  string
	 * @return IRepository
	 */
	public function & __get($name)
	{
		$var = $this->getRepositoryByName($name);
		return $var;
	}

}
