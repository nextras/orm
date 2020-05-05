<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use function array_unshift;


/**
 * Represents an SQL expression.
 * This class hold the main expression and its attributes.
 * If possible, also holds a reference to a backing property of the expression.
 */
class DbalExpressionResult
{
	/**
	 * Holds expression as the first argument and then all its arguments.
	 * @var mixed[]
	 * @phpstan-var list<mixed>
	 */
	public $args;

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
	 * @param mixed[] $args
	 * @phpstan-param list<mixed> $args
	 */
	public function __construct(
		array $args,
		bool $isHavingClause = false,
		?PropertyMetadata $propertyMetadata = null,
		?callable $valueNormalizer = null
	)
	{
		$this->args = $args;
		$this->isHavingClause = $isHavingClause;
		$this->propertyMetadata = $propertyMetadata;
		$this->valueNormalizer = $valueNormalizer;
	}


	/**
	 * Appends SQL expression to the original expression.
	 * If you need prepend or other complex expression, create new instance of DbalExpressionResult.
	 * @phpstan-param list<mixed> $args
	 */
	public function append(string $expression, ...$args): DbalExpressionResult
	{
		array_unshift($args, $this->args);
		array_unshift($args, "%ex $expression");
		return new DbalExpressionResult($args, $this->isHavingClause);
	}
}
