<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\StorageReflection;


class StringHelper
{
	public static function camelize(string $string): string
	{
		return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
	}


	public static function underscore(string $string): string
	{
		$result = preg_replace('#(\w)([A-Z])#', '$1_$2', $string);
		assert($result !== null);
		return strtolower($result);
	}
}
