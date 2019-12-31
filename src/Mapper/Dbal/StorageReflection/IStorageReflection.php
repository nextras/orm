<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal\StorageReflection;

use Nextras\Orm;


interface IStorageReflection extends Orm\Mapper\Memory\Conventions\IConventions
{
	/**
	 * Returns primary sequence name. If not supported nor present, returns null.
	 */
	public function getPrimarySequenceName(): ?string;


	/**
	 * Returns storage name for m:m relationship.
	 */
	public function getManyHasManyStorageName(Orm\Mapper\Memory\Conventions\IConventions $targetStorageReflection): string;


	/**
	 * Returns storage primary keys for m:m storage.
	 */
	public function getManyHasManyStoragePrimaryKeys(Orm\Mapper\Memory\Conventions\IConventions $targetStorageReflection): array;
}
