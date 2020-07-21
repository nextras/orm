<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use function array_combine;
use function array_map;
use function count;
use function in_array;
use function is_array;


class CompareNotEqualsFunction extends BaseCompareFunction
{
	/** @inheritDoc */
	protected function evaluateInPhp($sourceValue, $targetValue): bool
	{
		if (is_array($targetValue)) {
			return !in_array($sourceValue, $targetValue, true);
		} else {
			return $sourceValue !== $targetValue;
		}
	}


	/** @inheritDoc */
	protected function evaluateInDb(DbalExpressionResult $expression, $value): DbalExpressionResult
	{
		if (is_array($value)) {
			if (count($value) > 0) {
				// extract column names for multiOr simplification
				// array{%column, array<string>}
				$args = $expression->args;
				if (count($args) === 2 && $args[0] === '%column' && is_array($args[1])) {
					$columns = $args[1];
					$value = array_map(function ($value) use ($columns) {
						return array_combine($columns, $value);
					}, $value);
					return new DbalExpressionResult(['NOT (%multiOr)', $value], $expression->isHavingClause);
				} else {
					return $expression->append('NOT IN %any', $value);
				}
			} else {
				return new DbalExpressionResult(['1=1'], $expression->isHavingClause);
			}
		} elseif ($value === null) {
			return $expression->append('IS NOT NULL');
		} else {
			return $expression->append('!= %any', $value);
		}
	}
}
