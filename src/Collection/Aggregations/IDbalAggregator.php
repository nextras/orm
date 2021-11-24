<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;


interface IDbalAggregator
{
	public function aggregate(
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expressionResult
	): DbalExpressionResult;
}
