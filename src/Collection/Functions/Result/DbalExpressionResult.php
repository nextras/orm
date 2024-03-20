<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions\Result;


use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use function array_unshift;
use function array_values;


/**
 * Represents an SQL expression. This class hold the main expression and its attributes.
 *
 * The class is used either in WHERE clause or in HAVING clause, it is decided from the outside of this class,
 * yet this expression may force its using in HAVING clause by setting {@see $isHavingClause}.
 *
 * If possible, the expression holds a reference to a backing property of the expression {@see $propertyMetadata};
 * this is utilized to provide a value normalization.
 */
class DbalExpressionResult
{
	/**
	 * Normalizes the value for better PHP comparison, it considers the backing property type.
	 * @var (callable(mixed): mixed)|null
	 */
	public readonly mixed $valueNormalizer;


	/**
	 * @param literal-string $expression Holds expression separately from its arguments. Put Dbal's modifiers into the expression and arguments separately.
	 * @param list<mixed> $args Expression's arguments.
	 * @param list<DbalTableJoin> $joins
	 * @param list<Fqn> $groupBy List of columns used for grouping.
	 * @param list<Fqn> $columns List of columns used in the expression. If needed, this is later used to properly reference in GROUP BY clause.
	 * @param Aggregator<mixed>|null $aggregator Result aggregator that is applied later.
	 * @param bool $isHavingClause True if the expression represents HAVING clause instead of WHERE clause.
	 * @param PropertyMetadata|null $propertyMetadata Reference to backing property of the expression. If null, the expression is no more a simple property expression.
	 * @param (callable(mixed): mixed)|null $valueNormalizer Normalizes the value for better PHP comparison, it considers the backing property type.
	 * @param literal-string|null $dbalModifier Dbal modifier for particular column. Null if expression is a general expression.
	 */
	public function __construct(
		public readonly string $expression,
		public readonly array $args,
		public readonly array $joins = [],
		public readonly array $groupBy = [],
		public readonly array $columns = [],
		public readonly ?Aggregator $aggregator = null,
		public readonly bool $isHavingClause = false,
		public readonly ?PropertyMetadata $propertyMetadata = null,
		?callable $valueNormalizer = null,
		public readonly ?string $dbalModifier = null,
	)
	{
		$this->valueNormalizer = $valueNormalizer;
	}


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
			columns: $this->columns,
			aggregator: $this->aggregator,
			isHavingClause: $this->isHavingClause,
		);
	}


	/**
	 * Applies the aggregator and returns modified expression result.
	 */
	public function applyAggregator(QueryBuilder $queryBuilder, ExpressionContext $context): DbalExpressionResult
	{
		return $this->aggregator?->aggregateExpression($queryBuilder, $this, $context) ?? $this;
	}
}
