<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Memory\Conventions;

use Nette\SmartObject;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\Memory\Conventions\IConventions;


class Conventions implements IConventions
{
	use SmartObject;


	/** @var IMapper */
	private $mapper;

	/**
	 * @var string[]
	 * @phpstan-var list<string>
	 */
	private $primaryKeys;


	/**
	 * @param string[] $primaryKeys
	 * @phpstan-param list<string> $primaryKeys
	 */
	public function __construct(IMapper $mapper, array $primaryKeys)
	{
		$this->mapper = $mapper;
		$this->primaryKeys = $primaryKeys;
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
