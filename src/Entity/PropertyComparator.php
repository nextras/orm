<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


/**
 * This interface can be implemented by a {@see IPropertyContainer} (a property wrapper) that encapsulates custom types.
 */
interface PropertyComparator
{
	/**
	 * Compares its two values if they are equal. This enables equality comparison for multi-property values,
	 * like primary-proxied $id, when {@see compare} is not allowed.
	 */
	function equals(mixed $a, mixed $b): bool;


	/**
	 * Compares its two arguments for order. Returns zero if the arguments are equal, a negative number if
	 * the first argument is less than the second, or a positive number if the first argument is greater
	 * than the second.
	 */
	function compare(mixed $a, mixed $b): int;
}
