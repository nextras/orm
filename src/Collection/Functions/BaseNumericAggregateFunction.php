<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Aggregations\NumericAggregator;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use function assert;
use function count;
use function is_array;
use function is_string;


abstract class BaseNumericAggregateFunction implements CollectionFunction
{
	protected function __construct(
		private readonly NumericAggregator $aggregator,
	)
	{
	}


	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?Aggregator $aggregator = null,
	): ArrayExpressionResult
	{
		assert(count($args) === 1 && is_string($args[0]));

		$valueReference = $helper->getValue($entity, $args[0], $aggregator);
		if ($valueReference->aggregator === null) {
			throw new InvalidArgumentException('Aggregation has to be called over has many relationship.');
		}
		assert(is_array($valueReference->value));

		return new ArrayExpressionResult(
			value: $this->aggregator->aggregateValues($valueReference->value),
		);
	}


	public function processDbalExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?Aggregator $aggregator = null,
	): DbalExpressionResult
	{
		assert(count($args) === 1 && is_string($args[0]));

		if ($aggregator !== null) {
			throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
		}

		return $helper->processExpression($builder, $args[0], $this->aggregator)
			->applyAggregator(ExpressionContext::ValueExpression);
	}
}
