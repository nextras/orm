<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\StorageReflection;

use Nextras\Orm\Mapper\IMapper;


interface IDbStorageReflection extends IStorageReflection
{

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
