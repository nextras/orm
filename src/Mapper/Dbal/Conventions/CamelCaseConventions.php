<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal\Conventions;


use Nextras\Orm\Mapper\Dbal\Conventions\Conventions;


class CamelCaseConventions extends Conventions
{
	protected function formatStorageKey(string $key): string
	{
		return $key;
	}


	protected function formatEntityKey(string $key): string
	{
		return $key;
	}


	protected function formatEntityForeignKey(string $key): string
	{
		if (substr($key, -2) === 'Id') {
			$key = substr($key, 0, -2);
		}
		return $this->formatEntityKey($key);
	}
}
