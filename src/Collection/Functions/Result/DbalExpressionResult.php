<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions\Result;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use function array_unshift;
use function array_values;


/**
 * Represents an SQL expression.
 * This class hold the main expression and its attributes.
 * If possible, also holds a reference to a backing property of the expression.
 */
class DbalExpressionResult
{
	/**
	 * Holds expression separately from its arguments.
	 * @var literal-string
	 */
	public readonly string $expression;

	/**
	 * Expression's arguments.
	 * @var list<mixed>
	 */
	public readonly mixed $args;

	/**
	 * @var DbalTableJoin[]
	 */
	public readonly array $joins;

	/**
	 * List of arguments possible to pass to %ex modifier.
	 * Those grouping expressions are applied iff the $isHavingClause is true.
	 * @var array<array<mixed>>
	 */
	public readonly array $groupBy;

	/**
	 * Result aggregator.
	 */
	public readonly ?IDbalAggregator $aggregator;

	/**
	 * Bool if the expression will be incorporated into WHERE or HAVING clause.
	 */
	public readonly bool $isHavingClause;

	/**
	 * Reference to backing property of the expression.
	 * If null, the expression is no more a simple property expression.
	 */
	public readonly ?PropertyMetadata $propertyMetadata;

	/**
	 * Dbal modifier for particular column. Null if expression is a general expression.
	 * @var literal-string|null
	 */
	public readonly ?string $dbalModifier;

	/**
	 * @param literal-string $expression
	 * @param list<mixed> $args
	 * @param DbalTableJoin[] $joins
	 * @param array<array<mixed>> $groupBy
	 * @param bool $isHavingClause
	 * @param literal-string $dbalModifier
	 */
	public function __construct(
		string $expression,
		array $args,
		array $joins = [],
		array $groupBy = [],
		?IDbalAggregator $aggregator = null,
		bool $isHavingClause = false,
		?PropertyMetadata $propertyMetadata = null,
		?callable $valueNormalizer = null,
		?string $dbalModifier = null,
	)
	{
		$this->expression = $expression;
		$this->args = $args;
		$this->aggregator = $aggregator;
		$this->joins = $joins;
		$this->groupBy = $groupBy;
		$this->isHavingClause = $isHavingClause;
		$this->propertyMetadata = $propertyMetadata;
		$this->valueNormalizer = $valueNormalizer;
		$this->dbalModifier = $dbalModifier;

		if ($aggregator !== null && !$isHavingClause) {
			throw new InvalidArgumentException('Dbal expression with aggregator is expected to be defined as HAVING clause.');
		}
	}


	/**
	 * Value normalizer callback for proper matching backing property type.
	 * @var (callable(mixed): mixed)|null
	 */
	public readonly mixed $valueNormalizer;


	/**
	 * Appends SQL expression to the original expression.
	 * If you need prepend or other complex expression, create new instance of DbalExpressionResult.
	 * @param literal-string $expression
	 * @param mixed ...$args
	 */
	public function append(string $expression, ...$args): DbalExpressionResult
	{
		$args = array_values(array_merge($this->args, $args));
		return $this->withArgs("{$this->expression} $expression", $args);
	}


	/**
	 * Returns all arguments including the expression.
	 * Suitable as an `%ex` modifier argument.
	 * @return array<mixed>
	 */
	public function getArgumentsForExpansion(): array
	{
		$args = $this->args;
		array_unshift($args, $this->expression);
		return $args;
	}


	/**
	 * Creates a new DbalExpression from the passed $args and keeps the original expression
	 * properties (joins, aggregator, ...).
	 * @param literal-string $expression
	 * @param list<mixed> $args
	 */
	public function withArgs(string $expression, array $args): DbalExpressionResult
	{
		return new DbalExpressionResult(
			expression: $expression,
			args: $args,
			joins: $this->joins,
			groupBy: $this->groupBy,
			aggregator: $this->aggregator,
			isHavingClause: $this->isHavingClause,
		);
	}


	/**
	 * Applies the aggregator and returns modified expression result.
	 */
	public function applyAggregator(QueryBuilder $queryBuilder): DbalExpressionResult
	{
		if ($this->aggregator === null) {
			return $this;
		}

		return $this->aggregator->aggregateExpression($queryBuilder, $this);
	}
}
