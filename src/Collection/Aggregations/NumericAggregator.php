<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;


/**
 * @internal
 * @implements IArrayAggregator<number>
 */
class NumericAggregator implements IDbalAggregator, IArrayAggregator
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
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expression,
		ExpressionContext $context,
	): DbalExpressionResult
	{
		return new DbalExpressionResult(
			expression: "{$this->dbalAggregationFunction}($expression->expression)",
			args: $expression->args,
			joins: $expression->joins,
			groupBy: $expression->groupBy,
			isHavingClause: true,
		);
	}
}
