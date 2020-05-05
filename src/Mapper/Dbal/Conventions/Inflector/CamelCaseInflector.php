<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal\Conventions\Inflector;


class CamelCaseInflector implements IInflector
{
	public function formatStorageKey(string $key): string
	{
		return $key;
	}


	public function formatEntityKey(string $key): string
	{
		return $key;
	}


	public function formatEntityForeignKey(string $key): string
	{
		if (substr($key, -2) === 'Id') {
			$key = substr($key, 0, -2);
		}
		return $this->formatEntityKey($key);
	}
}
