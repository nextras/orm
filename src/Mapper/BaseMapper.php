<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper;

use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\StorageReflection\IDbStorageReflection;
use Nextras\Orm\InvalidStateException;
use Nette\Object;
use stdClass;


/**
 * Base Mapper.
 */
abstract class BaseMapper extends Object implements IMapper
{
	/** @var string */
	protected $tableName;

	/** @var IDbStorageReflection */
	protected $storageReflection;

	/** @var stdClass */
	protected $collectionCache;

	/** @var IRepository */
	private $repository;


	final public function setRepository(IRepository $repository)
	{
		if ($this->repository && $this->repository !== $repository) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is already attached to repository.");
		}

		$this->repository = $repository;
	}


	final public function getRepository()
	{
		if (!$this->repository) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is not attached to repository.");
		}

		return $this->repository;
	}


	public function getTableName()
	{
		if (!$this->tableName) {
			$this->tableName = $this->getStorageReflection()->getStorageName();
		}

		return $this->tableName;
	}


	public function getStorageReflection()
	{
		if ($this->storageReflection === NULL) {
			$this->storageReflection = $this->createStorageReflection();
		}

		return $this->storageReflection;
	}


	abstract protected function createStorageReflection();


	public function getCollectionCache()
	{
		return $this->collectionCache;
	}
}
