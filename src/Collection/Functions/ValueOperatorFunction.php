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
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
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


	/**
	 * @param array<mixed> $args
	 */
	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args
	): DbalExpressionResult
	{
		\assert(\count($args) === 3);

		$operator = $args[0];
		$expression = $helper->processPropertyExpr($builder, $args[1]);

		if ($expression->valueNormalizer !== null) {
			$cb = $expression->valueNormalizer;
			$value = $cb($args[2]);
		} else {
			$value = $args[2];
		}

		// extract column names for multiOr simplification
		$eArgs = $expression->args;
		if (
			\count($eArgs) === 2
			&& $eArgs[0] === '%column'
			&& \is_array($eArgs[1])
			&& \is_string($eArgs[1][0])
		) {
			$columns = $eArgs[1];
		} else {
			$columns = null;
		}

		if ($operator === ConditionParserHelper::OPERATOR_EQUAL) {
			if (\is_array($value)) {
				if ($value) {
					if ($columns !== null) {
						$value = \array_map(function ($value) use ($columns) {
							return \array_combine($columns, $value);
						}, $value);
						return new DbalExpressionResult(['%multiOr', $value], $expression->isHavingClause);
					} else {
						return $expression->append('IN %any', $value);
					}
				} else {
					return new DbalExpressionResult(['1=0'], $expression->isHavingClause);
				}
			} elseif ($value === null) {
				return $expression->append('IS NULL');
			} else {
				return $expression->append('= %any', $value);
			}

		} elseif ($operator === ConditionParserHelper::OPERATOR_NOT_EQUAL) {
			if (\is_array($value)) {
				if ($value) {
					if ($columns !== null) {
						$value = \array_map(function ($value) use ($columns) {
							return \array_combine($columns, $value);
						}, $value);
						return new DbalExpressionResult(['NOT (%multiOr)', $value], $expression->isHavingClause);
					} else {
						return $expression->append('NOT IN %any', $value);
					}
				} else {
					return new DbalExpressionResult(['1=1'], $expression->isHavingClause);
				}
			} elseif ($value === null) {
				return $expression->append('IS NOT NULL');
			} else {
				return $expression->append('!= %any', $value);
			}

		} else {
			return $expression->append("$operator %any", $value);
		}
	}
}
