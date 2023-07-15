<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;


class CompareGreaterThanFunction extends BaseCompareFunction
{
	protected function evaluateInPhp(mixed $sourceValue, mixed $targetValue): bool
	{
		return $sourceValue > $targetValue;
	}


	protected function evaluateInDb(
		DbalExpressionResult $expression,
		mixed $value,
		string $modifier,
	): DbalExpressionResult
	{
		return $expression->append("> $modifier", $value);
	}
}
