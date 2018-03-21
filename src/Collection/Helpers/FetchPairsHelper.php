<?php declare(strict_types = 1);

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
			$chain = self::parseExpr($value);
			foreach ($rows as $row) {
				$return[] = self::getProperty($row, $chain);
			}

		} elseif ($value === null) {
			$chain = self::parseExpr($key);
			foreach ($rows as $row) {
				$resultKey = self::getProperty($row, $chain);
				$return[is_object($resultKey) ? (string) $resultKey : $resultKey] = $row;
			}

		} else {
			$valueChain = self::parseExpr($value);
			$keyChain = self::parseExpr($key);
			foreach ($rows as $row) {
				$resultKey = self::getProperty($row, $keyChain);
				$resultValue = self::getProperty($row, $valueChain);
				$return[is_object($resultKey) ? (string) $resultKey : $resultKey] = $resultValue;
			}
		}

		return $return;
	}


	private static function parseExpr($expr)
	{
		list($chain, $sourceEntity) = ConditionParserHelper::parsePropertyExpr($expr);

		if ($sourceEntity === null && count($chain) <= 0) {
			return $expr;
		}

		if ($sourceEntity !== null) {
			array_unshift($chain, $sourceEntity);
		}

		return $chain;
	}


	private static function getProperty($row, $chain)
	{
		if (is_string($chain)) {
			return $row->{$chain};
		}

		while ($chain) {
			$propertyName = array_shift($chain);
			$result = isset($result) ? $result->{$propertyName} : $row->{$propertyName};
		}

		return $result;
	}
}
