<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal\Conventions;


use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\Platforms\Data\Table;
use Nextras\Orm\Exception\InvalidStateException;


interface IConventions
{
	/**
	 * Returns storage table reflection.
	 */
	public function getStorageTable(): Table;


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


	/**
	 * Returns primary sequence name. If not supported nor present, returns null.
	 */
	public function getPrimarySequenceName(): ?string;


	/**
	 * Returns storage name for m:m relationship.
	 */
	public function getManyHasManyStorageName(IConventions $targetConventions): string|Fqn;


	/**
	 * Returns storage primary columns for m:m storage.
	 * The first column leads to primary (main) table, the second column to secondary table.
	 * @return array{string, string}
	 */
	public function getManyHasManyStoragePrimaryKeys(IConventions $targetConventions): array;


	/**
	 * @param (callable(mixed $value, string $newKey): mixed)|null $toEntityCb
	 * @param (callable(mixed $value, string $newKey): mixed)|null $toStorageCb
	 * @return static
	 * @throws InvalidStateException Throws exception if mapping was already defined.
	 */
	public function addMapping(
		string $entity,
		string $storage,
		?callable $toEntityCb = null,
		?callable $toStorageCb = null
	): IConventions;


	/**
	 * @param (callable(mixed $value, string $newKey): mixed)|null $toEntityCb
	 * @param (callable(mixed $value, string $newKey): mixed)|null $toStorageCb
	 * @return static
	 */
	public function setMapping(
		string $entity,
		string $storage,
		?callable $toEntityCb = null,
		?callable $toStorageCb = null
	): IConventions;


	/**
	 * Sets column modifier for data transformation to Nextras Dbal layer.
	 * @param literal-string $saveModifier
	 * @return static
	 */
	public function setModifier(string $storageKey, string $saveModifier): IConventions;


	/**
	 * Returns column's modifier for Nextras Dbal layer.
	 * @return literal-string|null
	 */
	public function getModifier(string $storageKey): ?string;
}
