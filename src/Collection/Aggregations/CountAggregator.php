<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalTableJoin;
use Nextras\Orm\Exception\InvalidStateException;


/**
 * @implements IArrayAggregator<bool>
 */
class CountAggregator implements IDbalAggregator, IArrayAggregator
{
	private int $atLeast;

	private int $atMost;

	/** @var literal-string */
	private string $aggregateKey;


	/**
	 * @param literal-string $aggregateKey
	 */
	public function __construct(
		int $atLeast,
		int $atMost,
		string $aggregateKey = 'count',
	)
	{
		$this->atLeast = $atLeast;
		$this->atMost = $atMost;
		$this->aggregateKey = $aggregateKey;
	}


	public function getAggregateKey(): string
	{
		return $this->aggregateKey;
	}


	public function aggregateValues(array $values): bool
	{
		$count = count(array_filter($values));
		return $count >= $this->atLeast && $count <= $this->atMost;
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

		$joins[] = new DbalTableJoin(
			toExpression: $join->toExpression,
			toArgs: $join->toArgs,
			toAlias: $join->toAlias,
			onExpression: "($join->onExpression) AND $expression->expression",
			onArgs: array_merge($join->onArgs, $expression->args),
			conventions: $join->conventions,
		);

		$primaryKey = $join->conventions->getStoragePrimaryKey()[0];

		return new DbalExpressionResult(
			expression: 'COUNT(%table.%column) >= %i AND COUNT(%table.%column) <= %i',
			args: [$join->toAlias, $primaryKey, $this->atLeast, $join->toAlias, $primaryKey, $this->atMost],
			joins: $joins,
			groupBy: $expression->groupBy,
			isHavingClause: true,
		);
	}
}
