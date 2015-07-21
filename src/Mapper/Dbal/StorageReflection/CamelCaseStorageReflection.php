<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal\StorageReflection;


class CamelCaseStorageReflection extends StorageReflection
{

	public function formatStorageKey($key)
	{
		return $key;
	}


	public function formatEntityKey($key)
	{
		return $key;
	}


	public function formatEntityForeignKey($key)
	{
		if (substr($key, -2) === 'Id'){
			$key = substr($key, 0, -2);
		}
		return $this->formatEntityKey($key);
	}

}
