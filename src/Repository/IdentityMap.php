<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
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

		$entity = $this->createEntity($data);
		$id = implode(',', (array) $entity->getPersistedId());

		if (isset($this->entities[$id])) {
			$this->repository->detach($entity);
			return $this->entities[$id] ?: NULL;
		}

		return $this->entities[$id] = $entity; // = intentionally
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


	/**
	 * @param  array
	 * @return IEntity
	 */
	protected function createEntity(array $data)
	{
		$data = $this->storageReflection->convertStorageToEntity($data);
		$entityClass = $this->repository->getEntityClassName($data);

		if (!isset($this->entityReflections[$entityClass])) {
			$this->entityReflections[$entityClass] = ClassType::from($entityClass);
		}

		$entity = $this->entityReflections[$entityClass]->newInstanceWithoutConstructor();
		$this->repository->attach($entity);
		$entity->fireEvent('onLoad', [$data]);
		return $entity;
	}

}
