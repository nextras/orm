<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Memory\Conventions;


use Nette\SmartObject;


class Conventions implements IConventions
{
	use SmartObject;


	/** @var list<string> */
	private $primaryKeys;


	/**
	 * @param list<string> $primaryKeys
	 */
	public function __construct(array $primaryKeys)
	{
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
