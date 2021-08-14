<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ArrayNoneAggregator;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalNoneAggregator;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\Helpers\IArrayAggregator;
use Nextras\Orm\Collection\Helpers\IDbalAggregator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidStateException;


class NoneAggregateFunction implements IArrayFunction, IQueryBuilderFunction
{
	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?IArrayAggregator $aggregator = null
	)
	{
		if ($aggregator !== null) {
			throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
		}

		return $helper->getValue($entity, $args[0], new ArrayNoneAggregator())->value;
	}


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

		return $helper->processPropertyExpr($builder, $args[0], new DbalNoneAggregator());
	}
}
