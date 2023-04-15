<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection\Parser;


/**
 * @internal
 */
class Token
{
	public const KEYWORD = 1;
	public const STRING = 2;
	public const LBRACKET = 3;
	public const RBRACKET = 4;
	public const EQUAL = 5;
	public const SEPARATOR = 6;
	public const WHITESPACE = 7;

	public function __construct(
		public readonly string $value,
		public readonly int $type,
		public readonly int $offset,
	)
	{
	}
}
