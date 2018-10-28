<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
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
			$valueChain = self::parseExpr($value);
			foreach ($rows as $row) {
				$return[] = self::getProperty($row, $valueChain);
			}

		} elseif ($value === null) {
			$valueChain = self::parseExpr($key);
			foreach ($rows as $row) {
				$keyResult = self::getProperty($row, $valueChain);
				$return[($keyResult instanceof DateTimeImmutable) ? (string) $keyResult : $keyResult] = $row;
			}

		} else {
			$keyChain = self::parseExpr($key);
			$valueChain = self::parseExpr($value);
			foreach ($rows as $row) {
				$keyResult = self::getProperty($row, $keyChain);
				$valueResult = self::getProperty($row, $valueChain);
				$return[($keyResult instanceof DateTimeImmutable) ? (string) $keyResult : $keyResult] = $valueResult;
			}
		}

		return $return;
	}


	private static function parseExpr($expr): array
	{
		[$chain] = ConditionParserHelper::parsePropertyExpr($expr);
		return $chain;
	}


	private static function getProperty(IEntity $row, array $chain)
	{
		$result = $row;
		$lastPropertyName = "";
		do {
			$propertyName = array_shift($chain);
			if (!$result instanceof IEntity) {
				throw new InvalidStateException("Part '$lastPropertyName' of the chain expression does not select IEntity value.");
			}
			$lastPropertyName = $propertyName;
			$result = $result->getValue($propertyName);
		} while (!empty($chain));
		return $result;
	}
}
