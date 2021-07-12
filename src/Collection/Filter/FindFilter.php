<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Filter;

use Closure;
use Nextras\Orm\Collection\Expression\LikeExpression;
use Nextras\Orm\Collection\Functions\IArrayFunction;
use Nextras\Orm\Collection\Functions\IQueryBuilderFunction;
use Nextras\Orm\Collection\ICollection;
use function array_merge;
use function array_unshift;
use function count;

class FindFilter
{

	/** @var array<mixed> */
	private $conditions = [];

	/** @var string */
	private $logicalOperator;

	public function __construct(string $logicalOperator = ICollection::AND)
	{
		$this->logicalOperator = $logicalOperator;
	}

	/**
	 * @param mixed $value
	 */
	public function equal(string $property, $value): void
	{
		$this->operator('', $property, $value);
	}

	/**
	 * @param mixed $value
	 */
	public function notEqual(string $property, $value): void
	{
		$this->operator('!=', $property, $value);
	}

	/**
	 * @param mixed $number
	 */
	public function greater(string $property, $number): void
	{
		$this->operator('>', $property, $number);
	}

	/**
	 * @param mixed $number
	 */
	public function greaterOrEqual(string $property, $number): void
	{
		$this->operator('>=', $property, $number);
	}

	/**
	 * @param mixed $number
	 */
	public function lower(string $property, $number): void
	{
		$this->operator('<', $property, $number);
	}

	/**
	 * @param mixed $number
	 */
	public function lowerOrEqual(string $property, $number): void
	{
		$this->operator('<=', $property, $number);
	}

	public function like(string $property, LikeExpression $expression): void
	{
		$this->operator('~', $property, $expression);
	}

	/**
	 * @param mixed $value
	 */
	public function operator(string $operator, string $property, $value): void
	{
		$this->raw([
			"{$property}{$operator}" => $value,
		]);
	}

	/**
	 * @param class-string<IArrayFunction|IQueryBuilderFunction> $function
	 * @param string|array<mixed>                                $expression
	 * @param mixed                                              $values
	 */
	public function function(string $function, $expression, ...$values): void
	{
		$this->raw($this->createFunction($function, $expression, ...$values));
	}

	/**
	 * @param class-string<IArrayFunction|IQueryBuilderFunction> $function
	 * @param string|array<mixed>                                $expression
	 * @param mixed                                              $values
	 * @return array<mixed>
	 */
	public function createFunction(string $function, $expression, ...$values): array
	{
		return array_merge([$function, $expression], $values);
	}

	/**
	 * @param array<mixed> $condition
	 */
	public function raw(array $condition): void
	{
		$this->conditions[] = $condition;
	}

	/**
	 * @param Closure(FindFilter): void $conditions
	 */
	public function and(Closure $conditions): void
	{
		$this->logicalOperator($conditions, ICollection::AND);
	}

	/**
	 * @param Closure(FindFilter): void $conditions
	 */
	public function or(Closure $conditions): void
	{
		$this->logicalOperator($conditions, ICollection::OR);
	}

	/**
	 * @param Closure(FindFilter): void $conditions
	 */
	private function logicalOperator(Closure $conditions, string $operator): void
	{
		$find = new FindFilter($operator);

		$conditions($find);

		$raw = $find->getConditions();

		if ($raw === []) {
			return;
		}

		$this->raw($raw);
	}

	/**
	 * @return array<mixed>
	 */
	public function getConditions(): array
	{
		// No conditions, empty result
		$count = count($this->conditions);
		if ($count === 0) {
			return [];
		}

		// Only condition is inner logical operator, optimize it
		if ($count === 1) {
			$key = $this->conditions[0][0] ?? null;
			if ($key === ICollection::AND || $key === ICollection::OR) {
				return $this->conditions[0];
			}
		}

		$conditions = $this->conditions;
		array_unshift($conditions, $this->logicalOperator);

		return $conditions;
	}

}
