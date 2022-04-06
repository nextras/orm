<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Helpers\DbalExpressionResult;


class CompareSmallerThanFunction extends BaseCompareFunction
{
	/** @inheritDoc */
	protected function evaluateInPhp($sourceValue, $targetValue): bool
	{
		return $sourceValue < $targetValue;
	}


	/** @inheritDoc */
	protected function evaluateInDb(DbalExpressionResult $expression, $value, string $modifier): DbalExpressionResult
	{
		return $expression->append("< $modifier", $value);
	}
}
