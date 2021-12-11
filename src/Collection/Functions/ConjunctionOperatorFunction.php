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
	): ArrayPropertyValueReference
	{
		[$normalized, $newAggregator] = $this->normalizeFunctions($args);
		if ($newAggregator !== null) {
			if ($aggregator !== null) throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
			if (!$newAggregator instanceof IArrayAggregator) throw new InvalidArgumentException('Array requires aggregation instance of IArrayAggregator.');
			$aggregator = $newAggregator;
		}

		/**
		 * The following code evaluates all operands of the AND operator and group them by their aggregators.
		 * If there is an aggregation, operand results to multi-value result.
		 * Then we apply the operator's function per each value of the for multi-value result of operands with the same
		 * aggregation.
		 */

		/** @var array<string, IArrayAggregator<bool>> $aggregators */
		$aggregators = [];
		$values = [];
		$sizes = [];

		foreach ($normalized as $arg) {
			$callback = $helper->createFilter($arg, $aggregator);
			$valueReference = $callback($entity);
			if ($valueReference->aggregator === null) {
				if ($valueReference->value == false) {
					return new ArrayPropertyValueReference(
					/* $result = */false,
						null,
						null
					);
				}
			} else {
				$key = $valueReference->aggregator->getAggregateKey();
				$aggregators[$key] = $valueReference->aggregator;
				$values[$key][] = $valueReference->value;
				$sizes[$key] = max($sizes[$key] ?? 0, count($valueReference->value));
			}
		}

		foreach (array_keys($aggregators) as $key) {
			$valuesBatch = [];
			$size = $sizes[$key];
			for ($i = 0; $i < $size; $i++) {
				$operands = [];
				foreach ($values[$key] as $value) {
					if (isset($value[$i])) {
						$operands[] = $value[$i];
					}
				}
				$valuesBatch[] = array_reduce($operands, function ($acc, $v): bool {
					return $acc && (bool) $v;
				}, true);
			}

			$aggregator = $aggregators[$key];
			$result = $aggregator->aggregateValues($valuesBatch);
			if ($result == false) {
				return new ArrayPropertyValueReference(
				/* $result = */false,
					null,
					null
				);
			}
		}

		return new ArrayPropertyValueReference(
		/* $result = */ true,
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
			'%and',
			$helper,
			$builder,
			$args,
			$aggregator
		);
	}
}
