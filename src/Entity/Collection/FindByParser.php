<?php

/**
 * This file is part of the Nextras\ORM library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Collection;

use Nextras\Orm\InvalidArgumentException;


class FindByParser
{

	/**
	 * Parses findBy*() & getBy*() calls.
	 * @param  string
	 * @param  array
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

		$where = array();
		foreach (explode('And', $expression) as $i => $part) {
			if (!array_key_exists($i, $args)) {
				throw new InvalidArgumentException("Missing argument for {$i}th parameter.");
			}

			$where[lcfirst($part)] = $args[$i];
		}

		$name = $method;
		$args = $where;
		return TRUE;
	}

}
