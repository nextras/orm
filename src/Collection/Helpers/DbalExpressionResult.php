<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use function array_unshift;


/**
 * Represents an SQL expression.
 * This class hold the main expression and its attributes.
 * If possible, also holds a reference to a backing property of the expression.
 */
class DbalExpressionResult
{
	/**
	 * Holds expression separately from its arguments.
	 * @var string
	 * @phpstan-var literal-string
	 */
	public $expression;

	/**
	 * Expression's arguments.
	 * @var mixed[]
	 * @phpstan-var list<mixed>
	 */
	public $args;

	/**
	 * @var DbalJoinEntry[]
	 */
	public $joins;

	/**
	 * @var IDbalAggregator|null
	 */
	public $aggregator;

	/**
	 * Bool if the expression will be incorporated into WHERE or HAVING clause.
	 * @var bool
	 */
	public $isHavingClause;

	/**
	 * Reference to backing property of the expression.
	 * If null, the expression is no more a simple property expression.
	 * @var PropertyMetadata|null
	 */
	public $propertyMetadata;

	/**
	 * Value normalizer callback for proper matching backing property type.
	 * @var callable|null
	 * @phpstan-var (callable(mixed): mixed)|null
	 */
	public $valueNormalizer;


	/**
	 * @phpstan-param literal-string $expression
	 * @param mixed[] $args
	 * @param DbalJoinEntry[] $joins
	 * @phpstan-param list<mixed> $args
	 * @param bool $isHavingClause
	 */
	public function __construct(
		string $expression,
		array $args,
		array $joins = [],
		?IDbalAggregator $aggregator = null,
		bool $isHavingClause = false,
		?PropertyMetadata $propertyMetadata = null,
		?callable $valueNormalizer = null
	)
	{
		$this->expression = $expression;
		$this->args = $args;
		$this->aggregator = $aggregator;
		$this->joins = $joins;
		$this->isHavingClause = $isHavingClause;
		$this->propertyMetadata = $propertyMetadata;
		$this->valueNormalizer = $valueNormalizer;

		if ($aggregator !== null && !$isHavingClause) {
			throw new InvalidArgumentException('Dbal expression with aggregator is expected to be defined as HAVING clause.');
		}
	}


	/**
	 * Appends SQL expression to the original expression.
	 * If you need prepend or other complex expression, create new instance of DbalExpressionResult.
	 * @phpstan-param literal-string $expression
	 * @phpstan-param list<mixed> $args
	 */
	public function append(string $expression, ...$args): DbalExpressionResult
	{
		$args = array_merge($this->args, $args);
		return $this->withArgs("{$this->expression} $expression", $args);
	}


	/**
	 * Returns all arguments including the expression.
	 * Suitable as an `%ex` modifier argument.
	 * @return array<mixed>
	 */
	public function getExpansionArguments(): array
	{
		$args = $this->args;
		array_unshift($args, $this->expression);
		return $args;
	}


	/**
	 * Creates a new DbalExpression from the passed $args and keeps the original expression
	 * properties (joins, aggregator, ...).
	 * @phpstan-param literal-string $expression
	 * @param array<mixed> $args
	 */
	public function withArgs(string $expression, array $args): DbalExpressionResult
	{
		return new DbalExpressionResult(
			$expression,
			$args,
			$this->joins,
			$this->aggregator,
			$this->isHavingClause,
			null,
			null
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

		return $this->aggregator->aggregate($queryBuilder, $this);
	}
}
