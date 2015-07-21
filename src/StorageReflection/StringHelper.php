<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\StorageReflection;


class StringHelper
{

	/**
	 * @param  string   $string
	 * @return string
	 */
	public static function camelize($string)
	{
		return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
	}


	/**
	 * @param  string
	 * @return string
	 */
	public static function underscore($string)
	{
		return strtolower(preg_replace('#(\w)([A-Z])#', '$1_$2', $string));
	}

}
