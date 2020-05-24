<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Helpers\DbalExpressionResult;


class CompareGreaterThanEqualsFunction extends BaseCompareFunction
{
	/** @inheritDoc */
	protected function evaluateInPhp($sourceValue, $targetValue): bool
	{
		return $sourceValue >= $targetValue;
	}


	/** @inheritDoc */
	protected function evaluateInDb(DbalExpressionResult $expression, $value): DbalExpressionResult
	{
		return $expression->append(">= %any", $value);
	}
}
