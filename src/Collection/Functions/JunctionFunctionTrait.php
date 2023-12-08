<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\IAggregator;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use function array_shift;


/**
 * @internal
 */
trait JunctionFunctionTrait
{
	/**
	 * Normalizes directly entered column => value expression to expression array.
	 * @param array<mixed> $args
	 * @return array{list<mixed>, IAggregator|null}
	 */
	protected function normalizeFunctions(array $args): array
	{
		$aggregator = null;
		if (($args[0] ?? null) instanceof IAggregator) {
			$aggregator = array_shift($args);
		}

		// Args passed as array values
		// Originally called as [ICollection::AND, ['id' => 1], ['name' => John]]
		// Currency passed as [['id' => 1], ['name' => John]
		if (isset($args[0])) {
			/** @var list<mixed> $args */
			return [$args, $aggregator];
		}

		// Args passed as keys
		// Originally called as [ICollection::AND, 'id' => 1, 'name!=' => John]
		// Currency passed as ['id' => 1, 'name' => John]
		/** @var array<string, mixed> $args */
		$processedArgs = [];
		foreach ($args as $argName => $argValue) {
			$functionCall = $this->conditionParser->parsePropertyOperator($argName);
			$functionCall[] = $argValue;
			$processedArgs[] = $functionCall;
		}
		return [$processedArgs, $aggregator];
	}


	/**
	 * @param literal-string $dbalModifier either %or or %and dbal modifier
	 * @param array<mixed> $args
	 */
	protected function processQueryBuilderExpressionWithModifier(
		string $dbalModifier,
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator,
	): DbalExpressionResult
	{
		$isHavingClause = false;
		$processedArgs = [];
		$joins = [];
		$groupBy = [];

		[$normalized, $newAggregator] = $this->normalizeFunctions($args);
		if ($newAggregator !== null) {
			if ($aggregator !== null) throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
			if (!$newAggregator instanceof IDbalAggregator) throw new InvalidArgumentException('Dbal requires aggregation instance of IDbalAggregator.');
			$aggregator = $newAggregator;
		}

		foreach ($normalized as $collectionFunctionArgs) {
			$expression = $helper->processExpression($builder, $collectionFunctionArgs, $aggregator);
			$expression = $expression->applyAggregator($builder);
			$processedArgs[] = $expression->getArgumentsForExpansion();
			$joins = array_merge($joins, $expression->joins);
			$groupBy = array_merge($groupBy, $expression->groupBy);
			$isHavingClause = $isHavingClause || $expression->isHavingClause;
		}

		return new DbalExpressionResult(
			expression: $dbalModifier,
			args: [$processedArgs],
			joins: $helper->mergeJoins($dbalModifier, $joins),
			groupBy: $groupBy,
			isHavingClause: $isHavingClause,
		);
	}
}
