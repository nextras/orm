<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\PropertyWrapper;


use BackedEnum;
use Nextras\Orm\Entity\ImmutableValuePropertyWrapper;
use Nextras\Orm\Exception\NullValueException;
use function array_key_first;
use function assert;
use function is_int;
use function is_string;
use function is_subclass_of;


final class BackedEnumWrapper extends ImmutableValuePropertyWrapper
{
	public function setInjectedValue($value): bool
	{
		if ($value === null && !$this->propertyMetadata->isNullable) {
			throw new NullValueException($this->propertyMetadata);
		}

		return parent::setInjectedValue($value);
	}


	public function convertToRawValue(mixed $value): mixed
	{
		if ($value === null) return null;
		$type = array_key_first($this->propertyMetadata->types);
		assert($value instanceof $type);
		assert($value instanceof BackedEnum);
		return $value->value;
	}


	public function convertFromRawValue(mixed $value): ?BackedEnum
	{
		if ($value === null) {
			if ($this->propertyMetadata->isNullable) return null;
			throw new NullValueException($this->propertyMetadata);
		}

		assert(is_int($value) || is_string($value));
		$type = array_key_first($this->propertyMetadata->types);
		assert(is_subclass_of($type, BackedEnum::class));
		return $type::from($value);
	}
}
