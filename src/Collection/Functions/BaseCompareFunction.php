<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\IArrayAggregator;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ArrayPropertyValueReference;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
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
	): ArrayPropertyValueReference
	{
		assert(count($args) === 2);

		$valueReference = $helper->getValue($entity, $args[0], $aggregator);
		if ($valueReference->propertyMetadata !== null) {
			$targetValue = $helper->normalizeValue($args[1], $valueReference->propertyMetadata, true);
		} else {
			$targetValue = $args[1];
		}

		if ($valueReference->aggregator !== null) {
			$values = array_map(
				function ($value) use ($targetValue): bool {
					return $this->evaluateInPhp($value, $targetValue);
				},
				$valueReference->value
			);
			return new ArrayPropertyValueReference(
				$values,
				$valueReference->aggregator,
				null
			);
		} else {
			return new ArrayPropertyValueReference(
				$this->evaluateInPhp($valueReference->value, $targetValue),
				null,
				null
			);
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

		return $this->evaluateInDb($expression, $value, $expression->dbalModifier ?? '%any');
	}


	/**
	 * @param mixed $sourceValue
	 * @param mixed $targetValue
	 */
	abstract protected function evaluateInPhp($sourceValue, $targetValue): bool;


	/**
	 * @param mixed $value
	 * @phpstan-param literal-string $modifier
	 */
	abstract protected function evaluateInDb(
		DbalExpressionResult $expression,
		$value,
		string $modifier
	): DbalExpressionResult;
}
