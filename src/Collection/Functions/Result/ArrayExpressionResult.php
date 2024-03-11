<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions\Result;


use Nextras\Orm\Collection\Aggregations\Aggregator;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;


/**
 * Represents immediate array expression result.
 * If possible, also holds a reference to a backing Entity's property of the expression.
 */
class ArrayExpressionResult
{
	/**
	 * Expression result value.
	 */
	public readonly mixed $value;

	/**
	 * Reference to backing property of the expression.
	 * If null, the expression is no more a simple property expression.
	 */
	public readonly ?PropertyMetadata $propertyMetadata;

	/**
	 * @var Aggregator<mixed>|null
	 */
	public readonly ?Aggregator $aggregator;


	/**
	 * @param mixed $value
	 * @param Aggregator<mixed>|null $aggregator
	 */
	public function __construct(
		$value,
		?Aggregator $aggregator = null,
		?PropertyMetadata $propertyMetadata = null,
	)
	{
		$this->value = $value;
		$this->propertyMetadata = $propertyMetadata;
		$this->aggregator = $aggregator;
	}
}
