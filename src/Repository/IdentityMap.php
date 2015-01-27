<?php

/**
 * This file is part of the Nextras\ORM library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Repository;

use Nette\Object;
use Nette\Reflection\ClassType;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\StorageReflection\IStorageReflection;
use Nextras\Orm\InvalidArgumentException;


class IdentityMap extends Object
{

	/** @var IRepository */
	private $repository;

	/** @var array of IEntity|bool */
	private $entities = [];

	/** @var IStorageReflection cached instance */
	private $storageReflection;

	/** @var mixed cached primary key */
	private $storagePrimaryKey;

	/** @var ClassType[] */
	private $entityReflections;

	/** @var IDependencyProvider */
	private $dependencyProvider;


	public function __construct(IRepository $repository, IDependencyProvider $dependencyProvider = NULL)
	{
		$this->repository = $repository;
		$this->dependencyProvider = $dependencyProvider;
	}


	public function hasById($id)
	{
		return isset($this->entities[implode(',', (array) $id)]);
	}


	public function getById($id)
	{
		$id = implode(',', (array) $id);
		if (!isset($this->entities[$id])) {
			return NULL;
		}

		return $this->entities[$id];
	}


	public function add(IEntity $entity)
	{
		$this->entities[implode(',', (array) $entity->getPersistedId())] = $entity;
	}


	public function remove($id)
	{
		$this->entities[implode(',', (array) $id)] = FALSE;
	}


	public function create($data)
	{
		if ($this->storagePrimaryKey === NULL) {
			$this->storageReflection = $this->repository->getMapper()->getStorageReflection();
			$this->storagePrimaryKey = (array) $this->storageReflection->getStoragePrimaryKey();
		}

		$id = [];
		foreach ($this->storagePrimaryKey as $key) {
			if (!isset($data[$key])) {
				throw new InvalidArgumentException("Data returned from storage does not contain primary value(s) for '$key' key.");
			}
			$id[] = $data[$key];
		}
		$id = implode(',', $id);

		if (isset($this->entities[$id]) && $this->entities[$id]) {
			return $this->entities[$id] ?: NULL;
		}

		$data = $this->storageReflection->convertStorageToEntity($data);
		$entityClass = $this->repository->getEntityClassName($data);

		if (!isset($this->entityReflections[$entityClass])) {
			$this->entityReflections[$entityClass] = ClassType::from($entityClass);
		}

		/** @var $entity IEntity */
		$entity = $this->entities[$id] = $this->entityReflections[$entityClass]->newInstanceWithoutConstructor();
		$this->repository->attach($entity);
		$entity->fireEvent('onLoad', [$data]);

		return $entity;
	}


	/**
	 * @return IEntity[]
	 */
	public function getAll()
	{
		return $this->entities;
	}


	public function check(IEntity $entity)
	{
		if (!in_array(get_class($entity), $this->repository->getEntityClassNames(), TRUE)) {
			throw new InvalidArgumentException("Entity '" . get_class($entity) . "' is not accepted by '" . get_class($this->repository) . "' repository.");
		}
	}


	public function destroyAllEntities()
	{
		foreach ($this->entities as $entity) {
			if ($entity) {
				$this->repository->detach($entity);
				$entity->fireEvent('onFree');
			}
		}

		$this->entities = [];
	}

}
