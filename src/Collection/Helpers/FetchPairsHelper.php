<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nextras\Orm\InvalidArgumentException;
use Traversable;


class FetchPairsHelper
{
	public static function process(Traversable $collection, $key = null, $value = null)
	{
		$return = [];
		$rows = iterator_to_array($collection);

		if ($key === null && $value === null) {
			throw new InvalidArgumentException('FetchPairsHelper requires defined key or value.');
		}

		if ($key === null) {
			foreach ($rows as $row) {
				$return[] = $row->{$value};
			}

		} elseif ($value === null) {
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
