<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;


/**
 * @internal
 * @implements Aggregator<number>
 */
class NumericAggregator implements Aggregator
{
	/**
	 * @param callable(array<number>): (number|null) $arrayAggregation
	 * @param literal-string $dbalAggregationFunction
	 */
	public function __construct(
		private readonly mixed $arrayAggregation,
		private readonly string $dbalAggregationFunction,
	)
	{
	}


	public function getAggregateKey(): string
	{
		return '_' . $this->dbalAggregationFunction;
	}


	public function aggregateValues(array $values): mixed
	{
		$cb = $this->arrayAggregation;
		return $cb($values);
	}


	public function aggregateExpression(
		DbalExpressionResult $expression,
		ExpressionContext $context,
	): DbalExpressionResult
	{
		return new DbalExpressionResult(
			expression: null,
			args: [],
			joins: $expression->joins,
			groupBy: $expression->groupBy,
			havingExpression: "{$this->dbalAggregationFunction}($expression->expression)",
			havingArgs: $expression->args,
		);
	}


	public function isHavingClauseRequired(): bool
	{
		return true;
	}
}
