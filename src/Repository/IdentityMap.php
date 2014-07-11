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
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\StorageReflection\IStorageReflection;
use Nextras\Orm\InvalidArgumentException;


class IdentityMap extends Object
{
	/** @var IRepository */
	private $repository;

	/** @var array */
	private $entities = [];

	/** @var array */
	private $newEntities = [];

	/** @var IStorageReflection cached instance */
	private $storageReflection;

	/** @var mixed cached primary key */
	private $storagePrimaryKey;

	/** @var ClassType[] */
	private $entityReflections;

	/** @var EntityMetadata[] */
	private $entityMetadata;


	public function __construct(IRepository $repository)
	{
		$this->repository = $repository;
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


	public function add($id, IEntity $entity)
	{
		$this->entities[implode(',', (array) $id)] = $entity;
	}


	public function remove($id)
	{
		$this->entities[implode(',', (array) $id)] = FALSE;
	}


	public function attach(IEntity $entity)
	{
		$this->newEntities[spl_object_hash($entity)] = $entity;
		$entity->fireEvent('onAttach', [$this->repository, MetadataStorage::get(get_class($entity))]);
	}


	public function detach(IEntity $entity)
	{
		unset($this->newEntities[spl_object_hash($entity)]);
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
			return $this->entities[$id];
		}

		$data = $this->storageReflection->convertStorageToEntity($data);
		$entityClass = $this->repository->getEntityClassName($data);

		if (!isset($this->entityReflections[$entityClass])) {
			$this->entityReflections[$entityClass] = ClassType::from($entityClass);
			$this->entityMetadata[$entityClass] = MetadataStorage::get($entityClass);
		}

		/** @var $entity IEntity */
		$entity = $this->entities[$id] = $this->entityReflections[$entityClass]->newInstanceWithoutConstructor();
		$entity->fireEvent('onLoad', [$this->repository, $this->entityMetadata[$entityClass], $data]);
		return $entity;
	}


	/**
	 * @return IEntity[]
	 */
	public function getAll()
	{
		return $this->entities;
	}


	/**
	 * @return IEntity[]
	 */
	public function getAllNew()
	{
		return array_values($this->newEntities);
	}


	/**
	 * @param  string   $class
	 * @return EntityMetadata
	 */
	public function getEntityMetadata($class)
	{
		if (!isset($this->entityMetadata[$class])) {
			$this->entityMetadata[$class] = MetadataStorage::get($class);
		}

		return $this->entityMetadata[$class];
	}

}
