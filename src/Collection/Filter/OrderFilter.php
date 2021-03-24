<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Filter;

use Nextras\Orm\Collection\Functions\IArrayFunction;
use Nextras\Orm\Collection\Functions\IQueryBuilderFunction;
use Nextras\Orm\Collection\ICollection;
use function array_merge;

class OrderFilter
{

	/** @var array<array{0:string|array<mixed>, 1:string}> */
	private $order = [];

	public function property(string $property, string $direction = ICollection::ASC): void
	{
		$this->raw($property, $direction);
	}

	/**
	 * @param class-string<IArrayFunction|IQueryBuilderFunction> $function
	 * @param string|array<mixed>                                $expression
	 * @param mixed                                              $values
	 */
	public function function(string $function, $expression, string $direction = ICollection::ASC, ...$values): void
	{
		$this->raw(
			$this->createFunction($function, $expression, ...$values),
			$direction
		);
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
	 * @param string|array<mixed> $expression
	 */
	public function raw($expression, string $direction = ICollection::ASC): void
	{
		$this->order[] = [$expression, $direction];
	}

	/**
	 * @return array<array{0:string|array<mixed>, 1:string}>
	 */
	public function getOrder(): array
	{
		return $this->order;
	}

}
