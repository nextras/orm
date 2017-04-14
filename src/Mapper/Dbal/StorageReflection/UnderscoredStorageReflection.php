<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal\StorageReflection;

use Nextras\Orm\StorageReflection\StringHelper;


class UnderscoredStorageReflection extends StorageReflection
{
	protected function formatStorageKey(string $key): string
	{
		return StringHelper::underscore($key);
	}


	protected function formatEntityKey(string $key): string
	{
		return StringHelper::camelize($key);
	}


	protected function formatEntityForeignKey(string $key): string
	{
		if (substr($key, -3) === '_id') {
			$key = substr($key, 0, -3);
		}
		return $this->formatEntityKey($key);
	}
}
