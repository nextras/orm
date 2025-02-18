<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalTableJoin;
use Nextras\Orm\Exception\InvalidArgumentException;
use function array_merge;
use function array_pop;


/**
 * @implements Aggregator<bool>
 */
class NoneAggregator implements Aggregator
{
	/** @var literal-string */
	private string $aggregateKey;


	/**
	 * @param literal-string $aggregateKey
	 */
	public function __construct(string $aggregateKey = 'none')
	{
		$this->aggregateKey = $aggregateKey;
	}


	public function getAggregateKey(): string
	{
		return $this->aggregateKey;
	}


	public function aggregateValues(array $values): bool
	{
		foreach ($values as $value) {
			if ($value) {
				return false;
			}
		}
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
			throw new InvalidArgumentException('None aggregation applied over expression without a relationship.');
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

		return new DbalExpressionResult(
			expression: null,
			args: [],
			joins: $joins,
			groupBy: $expression->groupBy,
			havingExpression: 'COUNT(%column) = 0',
			havingArgs: [$join->toPrimaryKey],
		);
	}


	public function isHavingClauseRequired(): bool
	{
		return true;
	}
}
