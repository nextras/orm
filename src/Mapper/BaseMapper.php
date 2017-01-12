<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper;

use Nette\SmartObject;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\StorageReflection\IStorageReflection;
use Nextras\Orm\StorageReflection\StringHelper;
use stdClass;


abstract class BaseMapper implements IMapper
{
	use SmartObject;

	/** @var string */
	protected $tableName;

	/** @var IStorageReflection */
	protected $storageReflection;

	/** @var stdClass */
	protected $collectionCache;

	/** @var IRepository */
	private $repository;


	public function setRepository(IRepository $repository)
	{
		if ($this->repository && $this->repository !== $repository) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is already attached to repository.");
		}

		$this->repository = $repository;
		$this->collectionCache = (object) null;
	}


	public function getRepository()
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
			$className = preg_replace('~^.+\\\\~', '', get_class($this));
			$tableName = str_replace('Mapper', '', $className);
			$this->tableName = StringHelper::underscore($tableName);
		}

		return $this->tableName;
	}


	public function getStorageReflection()
	{
		if ($this->storageReflection === null) {
			$this->storageReflection = $this->createStorageReflection();
		}

		return $this->storageReflection;
	}


	public function getCollectionCache()
	{
		return $this->collectionCache;
	}


	public function clearCollectionCache()
	{
		$this->collectionCache = (object) null;
	}


	public function flush()
	{
		$this->collectionCache = (object) null;
	}


	abstract protected function createStorageReflection();
}
