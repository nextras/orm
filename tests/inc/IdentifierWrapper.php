<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\ImmutableValuePropertyWrapper;
use Nextras\Orm\Entity\PropertyComparator;
use Nextras\Orm\Exception\InvalidArgumentException;


final class IdentifierWrapper extends ImmutableValuePropertyWrapper implements PropertyComparator
{
	public function convertToRawValue(mixed $value): mixed
	{
		// a wrapper of a primary key has to accept the raw value as well
		if ($value === null || is_int($value) || is_string($value)) return $value;
		if (!$value instanceof Identifier) {
			throw new InvalidArgumentException('Value must be of type Identifier.');
		}
		return $value->value;
	}


	public function convertFromRawValue(mixed $value): Identifier|null
	{
		if ($value === null || $value instanceof Identifier) return $value;
		return new Identifier($value);
	}


	public function equals(mixed $a, mixed $b): bool
	{
		return $this->compare($a, $b) === 0;
	}


	public function compare(mixed $a, mixed $b): int
	{
		return $this->convertToRawValue($a) <=> $this->convertToRawValue($b);
	}
}
