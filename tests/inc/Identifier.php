<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


/**
 * Value object for testing primary keys with property wrappers.
 */
final class Identifier
{
	public function __construct(
		public readonly int|string $value,
	)
	{
	}
}
