<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\Helpers\IDbalAggregator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use function assert;
use function count;
use function is_array;
use function is_string;


abstract class BaseAggregateFunction implements IArrayFunction, IQueryBuilderFunction
{
	/** @var string */
	private $sqlFunction;


	protected function __construct(string $sqlFunction)
	{
		$this->sqlFunction = $sqlFunction;
	}


	/**
	 * @param array<number> $values
	 * @return number|null
	 */
	abstract protected function calculateAggregation(array $values);


	public function processArrayExpression(ArrayCollectionHelper $helper, IEntity $entity, array $args)
	{
		assert(count($args) === 1 && is_string($args[0]));

		$valueReference = $helper->getValue($entity, $args[0]);
		if (!$valueReference->isMultiValue) {
			throw new InvalidArgumentException('Aggregation has to be called over has many relationship.');
		}
		assert(is_array($valueReference->value));

		return $this->calculateAggregation($valueReference->value);
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator = null
	): DbalExpressionResult
	{
		assert(count($args) === 1 && is_string($args[0]));

		if ($aggregator !== null) {
			throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
		}

		$aggregator = new class implements IDbalAggregator {
			/** @var string */
			public $sqlFunction;


			public function aggregate(
				QueryBuilder $queryBuilder,
				DbalExpressionResult $expression
			): DbalExpressionResult
			{
				return new DbalExpressionResult(
					["{$this->sqlFunction}(%ex)", $expression->args],
					$expression->joins,
					null,
					true,
					null,
					null
				);
			}
		};
		$aggregator->sqlFunction = $this->sqlFunction;

		return $helper->processPropertyExpr($builder, $args[0], $aggregator)->applyAggregator($builder);
	}
}
