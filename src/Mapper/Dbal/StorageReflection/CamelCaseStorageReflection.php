<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
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
		return $this->formatEntityKey(substr($key, 0, -2)); // remove Id suffix
	}

}
