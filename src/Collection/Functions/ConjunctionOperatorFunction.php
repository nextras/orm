<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ConditionParser;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Collection\Helpers\IArrayAggregator;
use Nextras\Orm\Collection\Helpers\IDbalAggregator;
use Nextras\Orm\Entity\IEntity;


class ConjunctionOperatorFunction implements IArrayFunction, IQueryBuilderFunction
{
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
		foreach ($this->normalizeFunctions($args) as $arg) {
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
		$isHavingClause = false;
		$processedArgs = [];
		$joins = [];

		foreach ($this->normalizeFunctions($args) as $collectionFunctionArgs) {
			$expression = $helper->processFilterFunction($builder, $collectionFunctionArgs, $aggregator);
			$expression = $expression->applyAggregator($builder);
			$processedArgs[] = $expression->args;
			$joins = array_merge($joins, $expression->joins);
			$isHavingClause = $isHavingClause || $expression->isHavingClause;
		}

		return new DbalExpressionResult(
			['%and', $processedArgs],
			$joins,
			null,
			$isHavingClause,
			null,
			null
		);
	}


	/**
	 * Normalizes directly entered column => value expression to expression array.
	 * @phpstan-param array<string, mixed>|list<mixed> $args
	 * @phpstan-return list<mixed>
	 */
	protected function normalizeFunctions(array $args): array
	{
		// Args passed as array values
		// [ICollection::AND, ['id' => 1], ['name' => John]]
		if (isset($args[0])) {
			/** @phpstan-var list<mixed> $args */
			return $args;
		}

		// Args passed as keys
		// [ICollection::AND, 'id' => 1, 'name!=' => John]
		/** @phpstan-var array<string, mixed> $args */
		$processedArgs = [];
		foreach ($args as $argName => $argValue) {
			$functionCall = $this->conditionParser->parsePropertyOperator($argName);
			$functionCall[] = $argValue;
			$processedArgs[] = $functionCall;
		}
		return $processedArgs;
	}
}
