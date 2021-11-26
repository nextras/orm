<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\IArrayAggregator;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ArrayPropertyValueReference;
use Nextras\Orm\Collection\Helpers\ConditionParser;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;


class DisjunctionOperatorFunction implements IArrayFunction, IQueryBuilderFunction
{
	use JunctionFunctionTrait;


	/** @var ConditionParser */
	private $conditionParser;


	public function __construct(ConditionParser $conditionParserHelper)
	{
		$this->conditionParser = $conditionParserHelper;
	}


	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?IArrayAggregator $aggregator = null
	): ArrayPropertyValueReference
	{
		[$normalized, $newAggregator] = $this->normalizeFunctions($args);
		if ($newAggregator !== null) {
			if ($aggregator !== null) throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
			if (!$newAggregator instanceof IArrayAggregator) throw new InvalidArgumentException('Array requires aggregation instance of IArrayAggregator.');
			$aggregator = $newAggregator;
		}

		foreach ($normalized as $arg) {
			$callback = $helper->createFilter($arg, $aggregator);
			$valueReference = $callback($entity);
			$valueReference = $valueReference->applyAggregator();
			if ($valueReference->value == true) { // intentionally ==
				return new ArrayPropertyValueReference(
				/* $result = */ true,
					null,
					null
				);
			}
		}

		return new ArrayPropertyValueReference(
		/* $result = */ false,
			null,
			null
		);
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator = null
	): DbalExpressionResult
	{
		return $this->processQueryBuilderExpressionWithModifier(
			'%or',
			$helper,
			$builder,
			$args,
			$aggregator
		);
	}
}
