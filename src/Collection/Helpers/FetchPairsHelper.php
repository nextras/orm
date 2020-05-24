<?php declare(strict_types = 1);

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

		$row = $rows[0] ?? null;
		if ($row === null) {
			return [];
		}
		assert($row instanceof IEntity);
		$conditionParser = $row->getRepository()->getConditionParser();

		if ($key === null) {
			assert($value !== null);
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
	 * @phpstan-return list<string>
	 */
	private static function parseExpr(ConditionParser $conditionParser, string $expr): array
	{
		[$chain] = $conditionParser->parsePropertyExpr($expr);
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
