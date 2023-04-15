<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Expression;


/**
 * Like expression wrapper for {@see \Nextras\Orm\Collection\Functions\CompareLikeFunction}.
 */
class LikeExpression
{
	/**
	 * Wraps input as a final LIKE filter.
	 * Special LIKE characters are not sanitized.
	 * It is not recommended to use raw LIKE expression with user-entered input.
	 */
	public static function raw(string $input): LikeExpression
	{
		return new self($input, self::MODE_RAW);
	}


	/**
	 * Wraps input as find-by-prefix filter (i.e. string may end 0-n other characters).
	 * Special LIKE characters are sanitized.
	 */
	public static function startsWith(string $input): LikeExpression
	{
		return new self($input, self::MODE_STARTS_WITH);
	}


	/**
	 * Wraps input as find-by-suffix filter (i.e. string may start 0-n other characters).
	 * Special LIKE characters are sanitized.
	 */
	public static function endsWith(string $input): LikeExpression
	{
		return new self($input, self::MODE_ENDS_WITH);
	}


	/**
	 * Wraps input as contains filter (i.e. string may start and end 0-n other characters).
	 * Special LIKE characters are sanitized.
	 */
	public static function contains(string $input): LikeExpression
	{
		return new self($input, self::MODE_CONTAINS);
	}


	public const MODE_RAW = 0;
	public const MODE_STARTS_WITH = 1;
	public const MODE_ENDS_WITH = 2;
	public const MODE_CONTAINS = 3;


	private function __construct(
		public readonly string $input,
		public readonly int $mode,
	)
	{
	}
}
