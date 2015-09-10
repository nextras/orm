<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nextras\Orm\Entity\IEntity;
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
				$rowKey = $row->{$key};
				if (is_object($rowKey)) {
					$rowKey = $rowKey instanceof IEntity ? implode('-', (array) $rowKey->getPersistedId()) : (string) $rowKey;
				}
				$return[$rowKey] = $row;
			}

		} else {
			foreach ($rows as $row) {
				$rowKey = $row->{$key};
				if (is_object($rowKey)) {
					$rowKey = $rowKey instanceof IEntity ? implode('-', (array) $rowKey->getPersistedId()) : (string) $rowKey;
				}
				$return[$rowKey] = $row->{$value};
			}
		}

		return $return;
	}

}
