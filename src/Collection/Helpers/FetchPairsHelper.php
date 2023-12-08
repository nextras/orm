<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Entity\Embeddable\IEmbeddable;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Traversable;
use function array_shift;


class FetchPairsHelper
{
	/**
	 * @param Traversable<IEntity> $collection
	 * @return array<int|string, mixed>
	 */
	public static function process(Traversable $collection, ?string $key, ?string $value): array
	{
		$return = [];
		$rows = iterator_to_array($collection);

		if ($key === null && $value === null) {
			throw new InvalidArgumentException('FetchPairsHelper requires defined key or value.');
		}

		$firstRow = $rows[0] ?? null;
		if ($firstRow === null) {
			return [];
		}
		$conditionParser = $firstRow->getRepository()->getConditionParser();

		if ($key === null) {
			$valueChain = self::parseExpr($conditionParser, $value);
			foreach ($rows as $row) {
				$return[] = self::getProperty($row, $valueChain);
			}

		} elseif ($value === null) {
			$valueChain = self::parseExpr($conditionParser, $key);
			foreach ($rows as $row) {
				$keyResult = self::getProperty($row, $valueChain);
				$return[($keyResult instanceof DateTimeImmutable) ? (string) $keyResult : $keyResult] = $row;
			}

		} else {
			$keyChain = self::parseExpr($conditionParser, $key);
			$valueChain = self::parseExpr($conditionParser, $value);
			foreach ($rows as $row) {
				$keyResult = self::getProperty($row, $keyChain);
				$valueResult = self::getProperty($row, $valueChain);
				$return[($keyResult instanceof DateTimeImmutable) ? (string) $keyResult : $keyResult] = $valueResult;
			}
		}

		return $return;
	}


	/**
	 * @return list<string>
	 */
	private static function parseExpr(ConditionParser $conditionParser, string $expr): array
	{
		[$chain] = $conditionParser->parsePropertyExpr($expr);
		return $chain;
	}


	/**
	 * @param list<string> $chain
	 */
	private static function getProperty(IEntity $row, array $chain): mixed
	{
		$result = $row;
		$lastPropertyName = "";
		while (count($chain) > 0) {
			$propertyName = array_shift($chain);
			if (!$result instanceof IEntity && !$result instanceof IEmbeddable) {
				throw new InvalidStateException("Part '$lastPropertyName' of the chain expression does not select an IEntity nor an IEmbeddable.");
			}
			$lastPropertyName = $propertyName;
			$result = $result->getValue($propertyName);
		}
		return $result;
	}
}
