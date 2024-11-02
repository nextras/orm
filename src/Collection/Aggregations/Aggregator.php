<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;


/**
 * @template T The type of the aggregation result value.
 */
interface Aggregator
{
	/**
	 * Returns a grouping "key" used to join multiple conditions/joins together.
	 *
	 * In SQL, it is used as table alias suffix.
	 *
	 * @return literal-string
	 */
	public function getAggregateKey(): string;


	/**
	 * @param array<T> $values
	 * @return T|null
	 */
	public function aggregateValues(array $values);


	public function aggregateExpression(
		DbalExpressionResult $expression,
		ExpressionContext $context,
	): DbalExpressionResult;


	public function isHavingClauseRequired(): bool;
}
