<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nextras\Orm\InvalidArgumentException;


class FindByParserHelper
{

	/**
	 * Parses findBy*() & getBy*() calls.
	 * @param  string $name
	 * @param  array  $args
	 * @return bool
	 * @throws InvalidArgumentException
	 */
	public static function parse(& $name, & $args)
	{
		if (substr($name, 0, 6) === 'findBy') {
			$method = 'findBy';
			$expression = substr($name, 6);
		} elseif (substr($name, 0, 5) === 'getBy') {
			$method = 'getBy';
			$expression = substr($name, 5);
		} else {
			return FALSE;
		}

		if (strlen($expression) === 0) {
			return FALSE;
		}

		$where = array();
		foreach (explode('And', $expression) as $i => $part) {
			if (!array_key_exists($i, $args)) {
				throw new InvalidArgumentException('Missing argument for ' . ($i + 1) . 'th parameter.');
			}

			$where[lcfirst($part)] = $args[$i];
		}

		$name = $method;
		$args = $where;
		return TRUE;
	}

}
