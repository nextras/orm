<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions\Result;


use Closure;
use Nextras\Dbal\Platforms\Data\Fqn;
use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Collection\Expression\ExpressionContext;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidStateException;
use function array_merge;
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
	 * @param literal-string|null $expression Holds expression separately from its arguments. Put Dbal's modifiers into the expression and arguments separately.
	 * @param list<mixed> $args Expression's arguments.
	 * @param list<DbalTableJoin> $joins
	 * @param list<Fqn> $groupBy List of columns used for grouping.
	 * @param literal-string|null $havingExpression Holds expression for HAVING clause separately from its arguments. Put Dbal's modifiers into the expression and arguments separately.
	 * @param list<mixed> $havingArgs HAVING clause expression's arguments.
	 * @param list<Fqn> $columns List of columns used in the expression. If needed, this is later used to properly reference in GROUP BY clause.
	 * @param Aggregator<mixed>|null $aggregator Result aggregator that is applied later.
	 * @param PropertyMetadata|null $propertyMetadata Reference to backing property of the expression. If null, the expression is no more a simple property expression.
	 * @param (callable(mixed): mixed)|null $valueNormalizer Normalizes the value for better PHP comparison, it considers the backing property type.
	 * @param literal-string|list<literal-string|null>|null $dbalModifier Dbal modifier for particular column. Array if multi-column. Null value means expression is a general expression.
	 * @param (Closure(ExpressionContext): DbalExpressionResult)|null $collectCallback
	 * @param-closure-this DbalExpressionResult $collectCallback
	 */
	public function __construct(
		public readonly string|null $expression,
		public readonly array $args,
		public readonly array $joins = [],
		public readonly array $groupBy = [],
		public readonly string|null $havingExpression = null,
		public readonly array $havingArgs = [],
		public readonly array $columns = [],
		public readonly ?Aggregator $aggregator = null,
		public readonly ?PropertyMetadata $propertyMetadata = null,
		?callable $valueNormalizer = null,
		public readonly string|array|null $dbalModifier = null,
		public readonly Closure|null $collectCallback = null,
	)
	{
		$this->valueNormalizer = $valueNormalizer;
	}


	/**
	 * Appends SQL expression to the original expression.
	 * If you need prepend or other complex expression, create new instance of DbalExpressionResult.
	 *
	 * It auto-detects if expression or havingExpression should be appended. If both them are used, it throws exception.
	 *
	 * @param literal-string $expression
	 * @param mixed ...$args
	 */
	public function append(string $expression, ...$args): DbalExpressionResult
	{
		if ($this->expression !== null && $this->havingExpression !== null) {
			throw new InvalidStateException(
				'Cannot append result to a DbalExpressionResult because the both $expression (' .
				$this->expression . ') and $havingExpression (' . $this->havingExpression . ')' .
				'are already defined. Modify expression manually using withArgs() or withHavingArgs(). ',
			);
		}

		if ($this->expression !== null) {
			$args = array_values(array_merge($this->args, $args));
			return $this->withArgs("{$this->expression} $expression", $args);
		} else {
			$args = array_values(array_merge($this->havingArgs, $args));
			return $this->withHavingArgs("{$this->havingExpression} $expression", $args);
		}
	}


	/**
	 * Returns all arguments including the expression.
	 * Suitable as an `%ex` modifier argument.
	 * @return array<mixed>
	 */
	public function getArgsForExpansion(): array
	{
		if ($this->expression === null) return [];
		$args = $this->args;
		array_unshift($args, $this->expression);
		return $args;
	}


	/**
	 * Returns all HAVING clause arguments including the HAVING expression.
	 * Suitable as an `%ex` modifier argument.
	 * @return array<mixed>
	 */
	public function getHavingArgsForExpansion(): array
	{
		if ($this->havingExpression === null) return [];
		$args = $this->havingArgs;
		array_unshift($args, $this->havingExpression);
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
			havingExpression: $this->havingExpression,
			havingArgs: $this->havingArgs,
			columns: $this->columns,
			aggregator: $this->aggregator,
		);
	}


	/**
	 * Creates a new DbalExpression from the passed $havingArgs and keeps the original having expression
	 * properties (joins, aggregator, ...).
	 * @param literal-string $havingExpression
	 * @param list<mixed> $havingArgs
	 */
	public function withHavingArgs(string $havingExpression, array $havingArgs): DbalExpressionResult
	{
		return new DbalExpressionResult(
			expression: $this->expression,
			args: $this->args,
			joins: $this->joins,
			groupBy: $this->groupBy,
			havingExpression: $havingExpression,
			havingArgs: $havingArgs,
			columns: $this->columns,
			aggregator: $this->aggregator,
		);
	}


	public function collect(ExpressionContext $context): DbalExpressionResult
	{
		if ($this->collectCallback !== null) {
			$collectFun = $this->collectCallback->bindTo($this);
			return $collectFun($context);
		}

		// When in OR expression with HAVING clause, lift simple non aggregated conditions
		// to the HAVING clause.
		// For aggregated expression, it is the responsibility of the aggregator.
		if (
			$context === ExpressionContext::FilterOrWithHavingClause
			&& $this->expression !== null
			&& $this->aggregator === null
		) {
			return new DbalExpressionResult(
				expression: null,
				args: [],
				joins: $this->joins,
				groupBy: array_merge($this->groupBy, $this->columns),
				havingExpression: $this->expression,
				havingArgs: $this->args,
				columns: [],
				aggregator: $this->aggregator,
				propertyMetadata: $this->propertyMetadata,
				valueNormalizer: $this->valueNormalizer,
				dbalModifier: $this->dbalModifier,
				collectCallback: null,
			);
		}

		return $this;
	}


	/**
	 * Applies the aggregator and returns modified expression result.
	 */
	public function applyAggregator(ExpressionContext $context): DbalExpressionResult
	{
		return $this->aggregator?->aggregateExpression($this, $context) ?? $this;
	}
}
