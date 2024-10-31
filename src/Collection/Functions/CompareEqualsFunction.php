<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Exception\InvalidArgumentException;
use function count;
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


	protected function multiEvaluateInPhp(array $values, mixed $targetValue): array
	{
		if ($targetValue === null && $values === []) {
			return [true];
		}
		return parent::multiEvaluateInPhp($values, $targetValue);
	}


	protected function evaluateInDb(
		DbalExpressionResult $expression,
		mixed $value,
		string|array|null $modifier,
	): DbalExpressionResult
	{
		if (is_array($value)) {
			if (count($value) > 0) {
				// Multi-column primary key handling
				// Construct multiOr simplification as array{list<Fqn>, modifiers: list<string>, values: list<list<mixed>>}
				$args = $expression->getArgsForExpansion();
				if (count($args) === 2 && $args[0] === '%column' && is_array($args[1]) && is_array($modifier)) {
					$columns = $args[1];
					$data = [];
					foreach ($value as $dataSet) {
						$set = [];
						foreach ($dataSet as $i => $dataSetValue) {
							$set[] = [$columns[$i], $dataSetValue, $modifier[$i] ?? null];
						}
						$data[] = $set;
					}
					return $expression->withArgs('%multiOr', [$data]);
				} else {
					if (is_array($modifier)) throw new InvalidArgumentException();
					$modifier = $modifier ?? '%any';
					if ($modifier !== '%any') $modifier .= '[]';
					return $expression->append("IN $modifier", $value);
				}
			} else {
				return $expression->withArgs('1=0', []);
			}
		} elseif ($value === null) {
			return $expression->append('IS NULL');
		} else {
			if (is_array($modifier)) throw new InvalidArgumentException();
			$modifier = $modifier ?? '%any';
			return $expression->append("= $modifier", $value);
		}
	}
}
