<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Entity\Embeddable\IEmbeddable;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Traversable;
use function array_shift;
use function assert;


class FetchPairsHelper
{
	/**
	 * @param Traversable<IEntity> $collection
	 * @return mixed[]
	 * @phpstan-return array<int|string, mixed>
	 */
	public static function process(Traversable $collection, ?string $key, ?string $value)
	{
		$return = [];
		$rows = iterator_to_array($collection);

		if ($key === null && $value === null) {
			throw new InvalidArgumentException('FetchPairsHelper requires defined key or value.');
		}

		if ($key === null) {
			assert($value !== null);
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


	/**
	 * @phpstan-return list<string>
	 */
	private static function parseExpr(string $expr): array
	{
		[$chain] = ConditionParserHelper::parsePropertyExpr($expr);
		return $chain;
	}


	/**
	 * @phpstan-param list<string> $chain
	 * @return mixed
	 */
	private static function getProperty(IEntity $row, array $chain)
	{
		$result = $row;
		$lastPropertyName = "";
		while (!empty($chain)) {
			$propertyName = array_shift($chain);
			if (!$result instanceof IEntity && !$result instanceof IEmbeddable) {
				throw new InvalidStateException("Part '$lastPropertyName' of the chain expression does not select an IEntity nor an IEmbeddable.");
			}
			$lastPropertyName = $propertyName;
			// @phpstan-ignore-next-line Bug in while & array_shift https://github.com/phpstan/phpstan/issues/2611
			$result = $result->getValue($propertyName);
		}
		return $result;
	}
}
