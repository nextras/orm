<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use function array_map;
use function assert;
use function count;


abstract class BaseCompareFunction implements CollectionFunction
{
	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?Aggregator $aggregator = null,
	): ArrayExpressionResult
	{
		assert(count($args) === 2);

		$valueReference = $helper->getValue($entity, $args[0], $aggregator);
		if ($valueReference->propertyMetadata !== null) {
			$targetValue = $helper->normalizeValue($args[1], $valueReference->propertyMetadata, true);
		} else {
			$targetValue = $args[1];
		}

		if ($valueReference->aggregator !== null) {
			$values = $this->multiEvaluateInPhp($valueReference->value, $targetValue);
			return new ArrayExpressionResult(
				value: $values,
				aggregator: $valueReference->aggregator,
				propertyMetadata: null,
			);
		} else {
			return new ArrayExpressionResult(
				value: $this->evaluateInPhp($valueReference->value, $targetValue),
				aggregator: null,
				propertyMetadata: null,
			);
		}
	}


	public function processDbalExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?Aggregator $aggregator = null,
	): DbalExpressionResult
	{
		assert(count($args) === 2);

		$expression = $helper->processExpression($builder, $args[0], $aggregator);

		if ($expression->valueNormalizer !== null) {
			$cb = $expression->valueNormalizer;
			$value = $cb($args[1]);
		} else {
			$value = $args[1];
		}

		return $this->evaluateInDb($expression, $value, $expression->dbalModifier);
	}


	abstract protected function evaluateInPhp(mixed $sourceValue, mixed $targetValue): bool;


	/**
	 * @param array<mixed> $values
	 * @return array<mixed>
	 */
	protected function multiEvaluateInPhp(array $values, mixed $targetValue): array
	{
		return array_map(
			function ($value) use ($targetValue): bool {
				return $this->evaluateInPhp($value, $targetValue);
			},
			$values,
		);
	}


	/**
	 * @param literal-string|array<literal-string|null>|null $modifier
	 */
	abstract protected function evaluateInDb(
		DbalExpressionResult $expression,
		mixed $value,
		string|array|null $modifier,
	): DbalExpressionResult;
}
