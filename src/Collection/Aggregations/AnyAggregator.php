<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalTableJoin;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use function array_merge;
use function array_pop;


/**
 * @implements IArrayAggregator<bool>
 */
class AnyAggregator implements IDbalAggregator, IArrayAggregator
{
	/** @var literal-string */
	private string $aggregateKey;


	/**
	 * @param literal-string $aggregateKey
	 */
	public function __construct(string $aggregateKey = 'any')
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
				return true;
			}
		}
		return false;
	}


	public function aggregateExpression(
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expression,
	): DbalExpressionResult
	{
		$joins = $expression->joins;
		$join = array_pop($joins);
		if ($join === null) {
			throw new InvalidStateException('Aggregation applied over expression without a relationship');
		}
		if (count($join->primaryKeys) === 0) {
			throw new InvalidArgumentException('Aggregation applied over a table join without specifying a primary key.');
		}
		if (count($join->primaryKeys) > 1) {
			throw new InvalidArgumentException(
				'Aggregation applied over a table join with multi column primary key; currently, this is not supported.',
			);
		}

		$joins[] = new DbalTableJoin(
			toExpression: $join->toExpression,
			toArgs: $join->toArgs,
			toAlias: $join->toAlias,
			onExpression: "($join->onExpression) AND $expression->expression",
			onArgs: array_merge($join->onArgs, $expression->args),
			primaryKeys: $join->primaryKeys,
		);

		return new DbalExpressionResult(
			expression: 'COUNT(%table.%column) > 0',
			args: [$join->toAlias, $join->primaryKeys[0]],
			joins: $joins,
			groupBy: $expression->groupBy,
			isHavingClause: true,
		);
	}
}
