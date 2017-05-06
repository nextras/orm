<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\StorageReflection;


interface IStorageReflection
{
	/**
	 * Returns storage name.
	 */
	public function getStorageName(): string;


	/**
	 * Returns storage primary key name.
	 */
	public function getStoragePrimaryKey(): array;


	/**
	 * Converts entity data to storage key format.
	 */
	public function convertEntityToStorage(array $in): array;


	/**
	 * Converts entity key name to storage key format.
	 */
	public function convertEntityToStorageKey(string $key): string;


	/**
	 * Converts storage data to entity key format.
	 */
	public function convertStorageToEntity(array $in): array;


	/**
	 * Converts storage key name to entity key format.
	 */
	public function convertStorageToEntityKey(string $key): string;
}
