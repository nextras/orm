<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


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
		DbalExpressionResult $expression,
		ExpressionContext $context,
	): DbalExpressionResult
	{
		$joins = $expression->joins;
		$join = array_pop($joins);
		if ($join === null) {
			throw new InvalidArgumentException('Count aggregation applied over expression without a relationship.');
		}
		if ($join->toPrimaryKey === null) {
			throw new InvalidArgumentException(
				'Aggregation applied over a table-join without specifying a toPrimaryKey.',
			);
		}

		$joins[] = new DbalTableJoin(
			toExpression: $join->toExpression,
			toArgs: $join->toArgs,
			toAlias: $join->toAlias,
			onExpression: "($join->onExpression) AND $expression->expression",
			onArgs: array_merge($join->onArgs, $expression->args),
		);

		if ($this->atLeast !== null && $this->atMost !== null) {
			return new DbalExpressionResult(
				expression: null,
				args: [],
				joins: $joins,
				groupBy: $expression->groupBy,
				havingExpression: 'COUNT(%column) >= %i AND COUNT(%column) <= %i',
				havingArgs: [
					$join->toPrimaryKey,
					$this->atLeast,
					$join->toPrimaryKey,
					$this->atMost,
				],
			);
		} elseif ($this->atMost !== null) {
			return new DbalExpressionResult(
				expression: null,
				args: [],
				joins: $joins,
				groupBy: $expression->groupBy,
				havingExpression: 'COUNT(%column) <= %i',
				havingArgs: [
					$join->toPrimaryKey,
					$this->atMost,
				],
			);
		} else {
			return new DbalExpressionResult(
				expression: null,
				args: [],
				joins: $joins,
				groupBy: $expression->groupBy,
				havingExpression: 'COUNT(%column) >= %i',
				havingArgs: [
					$join->toPrimaryKey,
					$this->atLeast,
				],
			);
		}
	}


	public function isHavingClauseRequired(): bool
	{
		return true;
	}
}
