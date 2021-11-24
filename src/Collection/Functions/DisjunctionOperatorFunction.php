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
	)
	{
		foreach ($this->normalizeFunctions($args) as $arg) {
			$callback = $helper->createFilter($arg, $aggregator);
			if ($callback($entity) == true) { // intentionally ==
				return true;
			}
		}
		return false;
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
