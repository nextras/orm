<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Entity\PropertyComparator;
use Nextras\Orm\Exception\InvalidArgumentException;
use function is_array;


class CompareSmallerThanFunction extends BaseCompareFunction
{
	protected function evaluateInPhp(mixed $sourceValue, mixed $targetValue, PropertyComparator|null $comparator): bool
	{
		if ($comparator === null) {
			return $sourceValue < $targetValue;
		} else {
			return $comparator->compare($sourceValue, $targetValue) === -1;
		}
	}


	protected function evaluateInDb(
		DbalExpressionResult $expression,
		mixed $value,
		string|array|null $modifier,
	): DbalExpressionResult
	{
		if (is_array($modifier)) throw new InvalidArgumentException();
		$modifier = $modifier ?? '%any';
		return $expression->append("< $modifier", $value);
	}
}
