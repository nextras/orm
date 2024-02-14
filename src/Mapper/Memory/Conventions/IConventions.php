<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Memory\Conventions;


interface IConventions
{
	/**
	 * Returns storage primary key name.
	 * @return list<string>
	 */
	public function getStoragePrimaryKey(): array;


	/**
	 * Converts entity data to storage key format.
	 * @param array<string, mixed> $in
	 * @return array<string, mixed>
	 */
	public function convertEntityToStorage(array $in): array;


	/**
	 * Converts entity key name to storage key format.
	 */
	public function convertEntityToStorageKey(string $key): string;


	/**
	 * Converts storage data to entity key format.
	 * @param array<string, mixed> $in
	 * @return array<string, mixed>
	 */
	public function convertStorageToEntity(array $in): array;


	/**
	 * Converts storage key name to entity key format.
	 */
	public function convertStorageToEntityKey(string $key): string;
}
