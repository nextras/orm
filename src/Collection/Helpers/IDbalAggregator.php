<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nextras\Dbal\QueryBuilder\QueryBuilder;


interface IDbalAggregator
{
	public function aggregate(
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expressionResult
	): DbalExpressionResult;
}
