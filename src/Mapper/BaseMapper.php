<?php declare(strict_types = 1);

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


abstract class BaseMapper implements IMapper
{
	use SmartObject;


	/** @var string */
	protected $tableName;

	/** @var IStorageReflection */
	protected $storageReflection;

	/** @var IRepository */
	private $repository;


	public function setRepository(IRepository $repository)
	{
		if ($this->repository && $this->repository !== $repository) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is already attached to repository.");
		}

		$this->repository = $repository;
	}


	public function getRepository(): IRepository
	{
		if (!$this->repository) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is not attached to repository.");
		}

		return $this->repository;
	}


	public function getTableName(): string
	{
		if (!$this->tableName) {
			$className = preg_replace('~^.+\\\\~', '', get_class($this));
			$tableName = str_replace('Mapper', '', $className);
			$this->tableName = StringHelper::underscore($tableName);
		}

		return $this->tableName;
	}


	public function getStorageReflection(): IStorageReflection
	{
		if ($this->storageReflection === null) {
			$this->storageReflection = $this->createStorageReflection();
		}

		return $this->storageReflection;
	}


	abstract protected function createStorageReflection();
}
