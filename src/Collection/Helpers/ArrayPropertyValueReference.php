<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


use Nextras\Orm\Entity\Reflection\PropertyMetadata;


/**
 * Represents immediate expression result.
 * If possible, also holds a reference to a backing property of the expression.
 */
class ArrayPropertyValueReference
{
	/**
	 * Expression result value.
	 * @var mixed
	 */
	public $value;

	/**
	 * Bool if expression evaluated to multiple values (e.g. through has-many relationship).
	 * @var bool
	 */
	public $isMultiValue;

	/**
	 * Reference to backing property of the expression.
	 * If null, the expression is no more a simple property expression.
	 * @var PropertyMetadata|null
	 */
	public $propertyMetadata;


	/**
	 * @param mixed $value
	 */
	public function __construct($value, bool $isMultiValue, ?PropertyMetadata $propertyMetadata)
	{
		$this->value = $value;
		$this->isMultiValue = $isMultiValue;
		$this->propertyMetadata = $propertyMetadata;
	}
}
