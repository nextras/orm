<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Exception\InvalidArgumentException;
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
					$value = array_map(function ($value) use ($columns): array {
						/** @var array<string, string>|false $combined */
						$combined = array_combine($columns, $value);
						if ($combined === false) {
							$pn = count($columns);
							$vn = count($value);
							throw new InvalidArgumentException("Number of values ($vn) does not match number of properties ($pn).");
						}
						return $combined;
					}, $value);
					return $expression->withArgs(['NOT (%multiOr)', $value]);
				} else {
					return $expression->append('NOT IN %any', $value);
				}
			} else {
				return $expression->withArgs(['1=1']);
			}
		} elseif ($value === null) {
			return $expression->append('IS NOT NULL');
		} else {
			return $expression->append('!= %any', $value);
		}
	}
}
