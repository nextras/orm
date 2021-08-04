<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayAnyAggregator;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\Helpers\IArrayAggregator;
use Nextras\Orm\Collection\Helpers\IDbalAggregator;
use Nextras\Orm\Entity\IEntity;
use function assert;
use function count;


abstract class BaseCompareFunction implements IArrayFunction, IQueryBuilderFunction
{
	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?IArrayAggregator $aggregator = null
	)
	{
		assert(count($args) === 2);

		$valueReference = $helper->getValue($entity, $args[0], $aggregator);
		if ($valueReference->propertyMetadata !== null) {
			$targetValue = $helper->normalizeValue($args[1], $valueReference->propertyMetadata, true);
		} else {
			$targetValue = $args[1];
		}

		if ($valueReference->isMultiValue) {
			$values = array_map(
				function ($value) use ($targetValue): bool {
					return $this->evaluateInPhp($value, $targetValue);
				},
				$valueReference->value
			);
			$aggregator = $valueReference->aggregator ?? new ArrayAnyAggregator();
			return $aggregator->aggregate($values);
		} else {
			return $this->evaluateInPhp($valueReference->value, $targetValue);
		}
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator = null
	): DbalExpressionResult
	{
		assert(count($args) === 2);

		$expression = $helper->processPropertyExpr($builder, $args[0], $aggregator);

		if ($expression->valueNormalizer !== null) {
			$cb = $expression->valueNormalizer;
			$value = $cb($args[1]);
		} else {
			$value = $args[1];
		}

		return $this->evaluateInDb($expression, $value);
	}


	/**
	 * @param mixed $sourceValue
	 * @param mixed $targetValue
	 */
	abstract protected function evaluateInPhp($sourceValue, $targetValue): bool;


	/**
	 * @param mixed $value
	 */
	abstract protected function evaluateInDb(DbalExpressionResult $expression, $value): DbalExpressionResult;
}
