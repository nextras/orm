<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;


/**
 * @internal
 */
trait JunctionFunctionTrait
{
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


	/**
	 * @param literal-string $dbalModifier either %or or %and dbal modifier
	 * @param array<int|string, mixed> $args
	 */
	protected function processQueryBuilderExpressionWithModifier(
		string $dbalModifier,
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator
	): DbalExpressionResult
	{
		$isHavingClause = false;
		$processedArgs = [];
		$joins = [];
		$groupBy = [];

		foreach ($this->normalizeFunctions($args) as $collectionFunctionArgs) {
			$expression = $helper->processFilterFunction($builder, $collectionFunctionArgs, $aggregator);
			$expression = $expression->applyAggregator($builder);
			$processedArgs[] = $expression->getExpansionArguments();
			$joins = array_merge($joins, $expression->joins);
			$groupBy = array_merge($groupBy, $expression->groupBy);
			$isHavingClause = $isHavingClause || $expression->isHavingClause;
		}

		return new DbalExpressionResult(
			$dbalModifier,
			[$processedArgs],
			$helper->mergeJoins($dbalModifier, $joins),
			$groupBy,
			null,
			$isHavingClause,
			null,
			null
		);
	}
}
