<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\IArrayAggregator;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ConditionParser;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;


class ConjunctionOperatorFunction implements IArrayFunction, IQueryBuilderFunction
{
	use JunctionFunctionTrait;


	/** @var ConditionParser */
	protected $conditionParser;


	public function __construct(ConditionParser $conditionParserHelper)
	{
		$this->conditionParser = $conditionParserHelper;
	}


	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?IArrayAggregator $aggregator = null
	)
	{
		[$normalized, $newAggregator] = $this->normalizeFunctions($args);
		if ($newAggregator !== null) {
			if ($aggregator !== null) throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
			if (!$newAggregator instanceof IArrayAggregator) throw new InvalidArgumentException('Array requires aggregation instance of IArrayAggregator.');
			$aggregator = $newAggregator;
		}

		foreach ($normalized as $arg) {
			$callback = $helper->createFilter($arg, $aggregator);
			if ($callback($entity) == false) { // intentionally ==
				return false;
			}
		}
		return true;
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator = null
	): DbalExpressionResult
	{
		return $this->processQueryBuilderExpressionWithModifier(
			'%and',
			$helper,
			$builder,
			$args,
			$aggregator
		);
	}
}
