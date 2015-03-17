<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Dbal\StorageReflection;

use Nextras\Orm\StorageReflection\StringHelper;


class UnderscoredStorageReflection extends StorageReflection
{

	public function formatStorageKey($key)
	{
		return StringHelper::underscore($key);
	}


	public function formatEntityKey($key)
	{
		return StringHelper::camelize($key);
	}


	public function formatEntityForeignKey($key)
	{
		if (substr($key, -3) === '_id') {
			$key = substr($key, 0, -3);
		}
		return $this->formatEntityKey($key);
	}

}
