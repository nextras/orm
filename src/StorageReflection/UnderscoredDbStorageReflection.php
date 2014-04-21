<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\StorageReflection;


class UnderscoredDbStorageReflection extends DbStorageReflection
{

	public function formatStorageKey($key)
	{
		return static::underscore($key);
	}


	public function formatEntityKey($key)
	{
		return static::camelize($key);
	}


	public function formatEntityForeignKey($key)
	{
		return $this->formatEntityKey(substr($key, 0, -3)); // remove _id suffix
	}

}
