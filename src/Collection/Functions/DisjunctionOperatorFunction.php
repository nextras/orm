<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ConditionParser;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidStateException;


class DisjunctionOperatorFunction implements CollectionFunction
{
	use JunctionFunctionTrait;


	public function __construct(
		private readonly ConditionParser $conditionParser,
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
		[$normalized, $newAggregator] = $this->normalizeFunctions($args);
		if ($newAggregator !== null) {
			if ($aggregator !== null) throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
			$aggregator = $newAggregator;
		}

		/** @var array<string, Aggregator<bool>> $aggregators */
		$aggregators = [];
		$values = [];
		$sizes = [];

		foreach ($normalized as $arg) {
			$callback = $helper->createFilter($arg, $aggregator);
			$valueReference = $callback($entity);
			if ($valueReference->aggregator === null) {
				if ($valueReference->value == true) { // @phpstan-ignore-line Loose comparison https://github.com/nextras/orm/issues/586
					return new ArrayExpressionResult(
						value: true,
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
				$valuesBatch[] = array_reduce($operands, function ($acc, $v) {
					return $acc || (bool) $v;
				}, false);
			}

			$aggregator = $aggregators[$key];
			$result = $aggregator->aggregateValues($valuesBatch);
			if ($result == true) { // @phpstan-ignore-line Loose comparison https://github.com/nextras/orm/issues/586
				return new ArrayExpressionResult(
					value: true,
				);
			}
		}

		return new ArrayExpressionResult(
			value: false,
		);
	}


	public function processDbalExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?Aggregator $aggregator = null,
	): DbalExpressionResult
	{
		return $this->processQueryBuilderExpressionWithModifier(
			dbalModifier: '%or',
			helper: $helper,
			builder: $builder,
			args: $args,
			aggregator: $aggregator,
		);
	}
}
