<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\DbalAnyAggregator;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\Helpers\IDbalAggregator;
use Nextras\Orm\Exception\InvalidStateException;


class AnyAggregateFunction implements IQueryBuilderFunction
{
	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator = null
	): DbalExpressionResult
	{
		if ($aggregator !== null) {
			throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
		}

		return $helper->processPropertyExpr($builder, $args[0], new DbalAnyAggregator());
	}
}
