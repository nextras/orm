<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal\StorageReflection;

use Nextras\Orm;
use Nextras\Orm\Mapper\IMapper;


interface IStorageReflection extends Orm\StorageReflection\IStorageReflection
{
	/**
	 * Returns primary sequence name. If not supported nor present, returns null.
	 * @return string|null
	 */
	public function getPrimarySequenceName();


	/**
	 * Returns storage name for m:n relationship.
	 * @param  IMapper  $target
	 * @return string
	 */
	public function getManyHasManyStorageName(IMapper $target);


	/**
	 * Returns storage primary keys for m:n storage.
	 * @param  IMapper  $target
	 * @return array
	 */
	public function getManyHasManyStoragePrimaryKeys(IMapper $target);
}
