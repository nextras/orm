<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;


interface IDbalAggregator extends IAggregator
{
	public function aggregateExpression(
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expressionResult
	): DbalExpressionResult;
}
