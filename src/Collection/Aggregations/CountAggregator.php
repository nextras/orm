<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalTableJoin;
use Nextras\Orm\Exception\InvalidArgumentException;
use function array_filter;
use function array_merge;
use function array_pop;
use function count;


/**
 * @implements Aggregator<bool>
 */
class CountAggregator implements Aggregator
{
	/**
	 * @param literal-string $aggregateKey
	 */
	public function __construct(
		private readonly ?int $atLeast,
		private readonly ?int $atMost,
		private readonly string $aggregateKey = 'count',
	)
	{
		if ($this->atLeast === null && $this->atMost === null) {
			throw new InvalidArgumentException("At least one of the limitations (\$atLeast or \$atMost) is required.");
		}
	}


	public function getAggregateKey(): string
	{
		return $this->aggregateKey;
	}


	public function aggregateValues(array $values): bool
	{
		$count = count(array_filter($values));
		if ($this->atLeast !== null && $count < $this->atLeast) return false;
		if ($this->atMost !== null && $count > $this->atMost) return false;
		return true;
	}


	public function aggregateExpression(
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expression,
		ExpressionContext $context,
	): DbalExpressionResult
	{
		$joins = $expression->joins;
		$join = array_pop($joins);
		if ($join === null) {
			throw new InvalidArgumentException('Count aggregation applied over expression without a relationship.');
		}
		if (count($join->groupByColumns) === 0) {
			throw new InvalidArgumentException(
				'Aggregation applied over a table join without specifying a group-by column (primary key).',
			);
		}
		if (count($join->groupByColumns) > 1) {
			throw new InvalidArgumentException(
				'Aggregation applied over a table join with multiple group-by columns; currently, this is not supported.',
			);
		}

		$joins[] = new DbalTableJoin(
			toExpression: $join->toExpression,
			toArgs: $join->toArgs,
			toAlias: $join->toAlias,
			onExpression: "($join->onExpression) AND $expression->expression",
			onArgs: array_merge($join->onArgs, $expression->args),
			groupByColumns: $join->groupByColumns,
		);

		if ($this->atLeast !== null && $this->atMost !== null) {
			return new DbalExpressionResult(
				expression: 'COUNT(%table.%column) >= %i AND COUNT(%table.%column) <= %i',
				args: [
					$join->toAlias,
					$join->groupByColumns[0],
					$this->atLeast,
					$join->toAlias,
					$join->groupByColumns[0],
					$this->atMost,
				],
				joins: $joins,
				groupBy: $expression->groupBy,
				isHavingClause: true,
			);
		} elseif ($this->atMost !== null) {
			return new DbalExpressionResult(
				expression: 'COUNT(%table.%column) <= %i',
				args: [
					$join->toAlias,
					$join->groupByColumns[0],
					$this->atMost,
				],
				joins: $joins,
				groupBy: $expression->groupBy,
				isHavingClause: true,
			);
		} else {
			return new DbalExpressionResult(
				expression: 'COUNT(%table.%column) >= %i',
				args: [
					$join->toAlias,
					$join->groupByColumns[0],
					$this->atLeast,
				],
				joins: $joins,
				groupBy: $expression->groupBy,
				isHavingClause: true,
			);
		}
	}
}
