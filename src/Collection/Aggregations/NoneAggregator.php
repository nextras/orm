<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalTableJoin;
use Nextras\Orm\Exception\InvalidArgumentException;
use function array_merge;
use function array_pop;
use function count;


/**
 * @implements IArrayAggregator<bool>
 */
class NoneAggregator implements IDbalAggregator, IArrayAggregator
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
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expression,
	): DbalExpressionResult
	{
		$joins = $expression->joins;
		$join = array_pop($joins);
		if ($join === null) {
			throw new InvalidArgumentException('None aggregation applied over expression without a relationship.');
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

		return new DbalExpressionResult(
			expression: 'COUNT(%table.%column) = 0',
			args: [$join->toAlias, $join->groupByColumns[0]],
			joins: $joins,
			groupBy: $expression->groupBy,
			isHavingClause: true,
		);
	}
}
