<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalJoinEntry;
use Nextras\Orm\Exception\InvalidStateException;


/**
 * @implements IArrayAggregator<bool>
 */
class CountAggregator implements IDbalAggregator, IArrayAggregator
{
	/** @var int */
	private $atLeast;

	/** @var int */
	private $atMost;

	/** @var literal-string */
	private $aggregateKey;


	/**
	 * @param literal-string $aggregateKey
	 */
	public function __construct(
		int $atLeast,
		int $atMost,
		string $aggregateKey = 'count'
	)
	{
		$this->atLeast = $atLeast;
		$this->atMost = $atMost;
		$this->aggregateKey = $aggregateKey;
	}


	public function getAggregateKey(): string
	{
		return $this->aggregateKey;
	}


	public function aggregateValues(array $values): bool
	{
		$count = count(array_filter($values));
		return $count >= $this->atLeast && $count <= $this->atMost;
	}


	public function aggregateExpression(
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expression
	): DbalExpressionResult
	{
		$joinExpression = $expression->expression;

		$joinArgs = $expression->args;
		$joins = $expression->joins;
		$join = array_pop($joins);
		if ($join === null) {
			throw new InvalidStateException('Aggregation applied over expression without a relationship');
		}

		$joins[] = new DbalJoinEntry(
			$join->toExpression,
			$join->toArgs,
			$join->toAlias,
			"($join->onExpression) AND $joinExpression",
			array_merge($join->onArgs, $joinArgs),
			$join->conventions
		);

		$primaryKey = $join->conventions->getStoragePrimaryKey()[0];

		return new DbalExpressionResult(
			'COUNT(%table.%column) >= %i AND COUNT(%table.%column) <= %i',
			[$join->toAlias, $primaryKey, $this->atLeast, $join->toAlias, $primaryKey, $this->atMost],
			$joins,
			$expression->groupBy,
			null,
			true,
			null,
			null
		);
	}
}
