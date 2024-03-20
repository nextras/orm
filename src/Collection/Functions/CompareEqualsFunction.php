<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use function count;
use function explode;
use function in_array;
use function is_array;


class CompareEqualsFunction extends BaseCompareFunction
{
	protected function evaluateInPhp(mixed $sourceValue, mixed $targetValue): bool
	{
		if (is_array($targetValue)) {
			return in_array($sourceValue, $targetValue, true);
		} else {
			return $sourceValue === $targetValue;
		}
	}


	protected function evaluateInDb(
		DbalExpressionResult $expression,
		mixed $value,
		string $modifier,
	): DbalExpressionResult
	{
		if (is_array($value)) {
			if (count($value) > 0) {
				// Multi-column primary key handling
				// Construct multiOr simplification as array{list<Fqn>, modifiers: list<string>, values: list<list<mixed>>}
				$args = $expression->getArgumentsForExpansion();
				if (count($args) === 2 && $args[0] === '%column' && is_array($args[1])) {
					$columns = $args[1];
					$modifiers = array_map(
						fn (string $modifier): ?string => strlen($modifier) === 0 ? null : $modifier,
						explode(',', $modifier)
					);
					$data = [];
					foreach ($value as $dataSet) {
						$set = [];
						foreach ($dataSet as $i => $dataSetValue) {
							$set[] = [$columns[$i], $dataSetValue, $modifiers[$i] ?? null];
						}
						$data[] = $set;
					}
					return $expression->withArgs('%multiOr', [$data]);
				} else {
					if ($modifier !== '%any') $modifier .= '[]';
					return $expression->append("IN $modifier", $value);
				}
			} else {
				return $expression->withArgs('1=0', []);
			}
		} elseif ($value === null) {
			return $expression->append('IS NULL');
		} else {
			return $expression->append("= $modifier", $value);
		}
	}
}
