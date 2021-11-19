<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Exception\InvalidStateException;
use function array_merge;
use function array_pop;
use function array_shift;


class DbalAnyAggregator implements IDbalAggregator
{
	public function aggregate(
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expression
	): DbalExpressionResult
	{
		$joinExpression = $expression->expression;

		$joinArgs = $expression->args;
		$joins = $expression->joins;
		$join = array_pop($joins);
		if ($join === null) {
			throw new InvalidStateException('Aggregation applied over expression without a relationship');
		}

		$joins[] = new DbalJoinEntry(
			$join->toExpression,
			$join->toArgs,
			$join->toAlias,
			"($join->onExpression) AND $joinExpression",
			array_merge($join->onArgs, $joinArgs),
			$join->conventions
		);

		$primaryKey = $join->conventions->getStoragePrimaryKey()[0];
		$queryBuilder->addGroupBy('%table.%column', $join->toAlias, $primaryKey);

		return new DbalExpressionResult(
			'COUNT(%table.%column) > 0',
			[$join->toAlias, $primaryKey],
			$joins,
			null,
			true,
			null,
			null
		);
	}
}
