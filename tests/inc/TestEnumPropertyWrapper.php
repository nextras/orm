<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use MabeEnum\Enum;
use Nextras\Orm\Entity\ImmutableValuePropertyWrapper;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;


class TestEnumPropertyWrapper extends ImmutableValuePropertyWrapper
{
	/** @var string */
	private $enumClass;


	public function __construct(PropertyMetadata $propertyMetadata)
	{
		parent::__construct($propertyMetadata);
		assert(count($propertyMetadata->types) === 1);
		$this->enumClass = key($propertyMetadata->types);
		assert(class_exists($this->enumClass));
	}


	public function convertToRawValue($value)
	{
		assert($value instanceof Enum);
		return $value->getValue();
	}


	public function convertFromRawValue($value)
	{
		$enumClass = $this->enumClass;
		return $enumClass::byValue($value);
	}
}
