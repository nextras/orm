<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\StorageReflection;

use Nette\SmartObject;
use Nextras\Orm\Mapper\IMapper;


class CommonReflection implements IStorageReflection
{
	use SmartObject;


	/** @var IMapper */
	private $mapper;

	/** @var string */
	private $storageName;

	/** @var array */
	private $primaryKeys;


	public function __construct(IMapper $mapper, string $storageName, array $primaryKeys)
	{
		$this->mapper = $mapper;
		$this->storageName = $storageName;
		$this->primaryKeys = $primaryKeys;
	}


	public function getStorageName(): string
	{
		return $this->storageName;
	}


	public function getStoragePrimaryKey(): array
	{
		return $this->primaryKeys;
	}


	public function convertEntityToStorage(array $data): array
	{
		return $data;
	}


	public function convertStorageToEntity(array $data): array
	{
		return $data;
	}


	public function convertEntityToStorageKey(string $key): string
	{
		return $key;
	}


	public function convertStorageToEntityKey(string $key): string
	{
		return $key;
	}
}
