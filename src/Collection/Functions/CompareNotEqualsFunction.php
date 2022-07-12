<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Exception\InvalidArgumentException;
use function array_combine;
use function array_map;
use function count;
use function explode;
use function in_array;
use function is_array;


class CompareNotEqualsFunction extends BaseCompareFunction
{
	protected function evaluateInPhp($sourceValue, $targetValue): bool
	{
		if (is_array($targetValue)) {
			return !in_array($sourceValue, $targetValue, true);
		} else {
			return $sourceValue !== $targetValue;
		}
	}


	protected function evaluateInDb(DbalExpressionResult $expression, $value, string $modifier): DbalExpressionResult
	{
		if (is_array($value)) {
			if (count($value) > 0) {
				// Multi-column primary key handling
				// extract column names for multiOr simplification
				// array{%column, array<string>}
				$args = $expression->getArgumentsForExpansion();
				if (count($args) === 2 && $args[0] === '%column' && is_array($args[1])) {
					$modifiers = explode(',', $modifier);
					$columns = [];
					foreach ($args[1] as $i => $column) {
						$columns[] = $column . $modifiers[$i];
					}
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
					return $expression->withArgs('NOT (%multiOr)', [$value]);
				} else {
					if ($modifier !== '%any') {
						$modifier .= '[]';
					}
					return $expression->append("NOT IN $modifier", $value);
				}
			} else {
				return $expression->withArgs('1=1', []);
			}
		} elseif ($value === null) {
			return $expression->append('IS NOT NULL');
		} else {
			return $expression->append("!= $modifier", $value);
		}
	}
}
