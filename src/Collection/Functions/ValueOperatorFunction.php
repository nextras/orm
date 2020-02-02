<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Functions;

use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\InvalidArgumentException;


class ValueOperatorFunction implements IArrayFunction, IQueryBuilderFunction
{
	public function processArrayExpression(ArrayCollectionHelper $helper, IEntity $entity, array $args)
	{
		assert(count($args) === 3);
		$operator = $args[0];
		$valueReference = $helper->getValue($entity, $args[1]);
		if ($valueReference === null) {
			return false;
		}

		if ($valueReference->propertyMetadata !== null) {
			$targetValue = $helper->normalizeValue($args[2], $valueReference->propertyMetadata, true);
		} else {
			$targetValue = $args[2];
		}

		if ($valueReference->isMultiValue) {
			foreach ($valueReference->value as $subValue) {
				if ($this->arrayEvaluate($operator, $targetValue, $subValue)) {
					return true;
				}
			}
			return false;
		} else {
			return $this->arrayEvaluate($operator, $targetValue, $valueReference->value);
		}
	}


	private function arrayEvaluate(string $operator, $targetValue, $sourceValue): bool
	{
		if ($operator === ConditionParserHelper::OPERATOR_EQUAL) {
			if (is_array($targetValue)) {
				return in_array($sourceValue, $targetValue, true);
			} else {
				return $sourceValue === $targetValue;
			}
		} elseif ($operator === ConditionParserHelper::OPERATOR_NOT_EQUAL) {
			if (is_array($targetValue)) {
				return !in_array($sourceValue, $targetValue, true);
			} else {
				return $sourceValue !== $targetValue;
			}
		} elseif ($operator === ConditionParserHelper::OPERATOR_GREATER) {
			return $sourceValue > $targetValue;
		} elseif ($operator === ConditionParserHelper::OPERATOR_EQUAL_OR_GREATER) {
			return $sourceValue >= $targetValue;
		} elseif ($operator === ConditionParserHelper::OPERATOR_SMALLER) {
			return $sourceValue < $targetValue;
		} elseif ($operator === ConditionParserHelper::OPERATOR_EQUAL_OR_SMALLER) {
			return $sourceValue <= $targetValue;
		} else {
			throw new InvalidArgumentException();
		}
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args
	): array
	{
		\assert(\count($args) === 3);
		$operator = $args[0];
		$columnReference = $helper->processPropertyExpr($builder, $args[1]);
		$placeholder = $columnReference->columnPlaceholder;
		$column = $columnReference->column;
		if ($columnReference->propertyMetadata === null) {
			$value = $args[2];
		} else {
			$value = $helper->normalizeValue($args[2], $columnReference);
		}

		if ($operator === ConditionParserHelper::OPERATOR_EQUAL) {
			if (\is_array($value)) {
				if ($value) {
					if (\is_array($column)) {
						$value = \array_map(function ($value) use ($column) {
							return \array_combine($column, $value);
						}, $value);
						return ['%multiOr', $value];
					} else {
						return ["$placeholder IN %any", $column, $value];
					}
				} else {
					return ['1=0'];
				}
			} elseif ($value === null) {
				return ["$placeholder IS NULL", $column];
			} else {
				return ["$placeholder = %any", $column, $value];
			}

		} elseif ($operator === ConditionParserHelper::OPERATOR_NOT_EQUAL) {
			if (\is_array($value)) {
				if ($value) {
					if (\is_array($column)) {
						$value = \array_map(function ($value) use ($column) {
							return \array_combine($column, $value);
						}, $value);
						return ['NOT (%multiOr)', $value];
					} else {
						return ["$placeholder NOT IN %any", $column, $value];
					}
				} else {
					return ['1=1'];
				}
			} elseif ($value === null) {
				return ["$placeholder IS NOT NULL", $column];
			} else {
				return ["$placeholder != %any", $column, $value];
			}

		} else {
			return ["$placeholder $operator %any", $column, $value];
		}
	}
}
