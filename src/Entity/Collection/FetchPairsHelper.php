<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Collection;

use Nextras\Orm\InvalidArgumentException;
use Traversable;


class FetchPairsHelper
{

	public static function process(Traversable $collection, $key = NULL, $value = NULL)
	{
		$return = [];
		$rows = iterator_to_array($collection);

		if ($key === NULL && $value === NULL) {
			throw new InvalidArgumentException('FetchPairsHelper requires defined key or value.');
		}

		if ($key === NULL) {
			foreach ($rows as $row) {
				$return[] = $row->{$value};
			}

		} elseif ($value === NULL) {
			foreach ($rows as $row) {
				$return[is_object($row->{$key}) ? (string) $row->{$key} : $row->{$key}] = $row;
			}

		} else {
			foreach ($rows as $row) {
				$return[is_object($row->{$key}) ? (string) $row->{$key} : $row->{$key}] = $row->{$value};
			}
		}

		return $return;
	}

}
