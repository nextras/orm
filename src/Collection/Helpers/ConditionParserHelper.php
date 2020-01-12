<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\InvalidArgumentException;


class ConditionParserHelper
{
	public const OPERATOR_EQUAL = '=';
	public const OPERATOR_NOT_EQUAL = '!=';
	public const OPERATOR_GREATER = '>';
	public const OPERATOR_EQUAL_OR_GREATER = '>=';
	public const OPERATOR_SMALLER = '<';
	public const OPERATOR_EQUAL_OR_SMALLER = '<=';


	public static function parsePropertyOperator(string $condition): array
	{
		if (!\preg_match('#^(.+?)(!=|<=|>=|=|>|<)?$#', $condition, $matches)) {
			return [$condition, self::OPERATOR_EQUAL];
		} else {
			return [$matches[1], $matches[2] ?? self::OPERATOR_EQUAL];
		}
	}


	/**
	 * @return array{array<string>, class-string<IEntity>|null}
	 */
	public static function parsePropertyExpr(string $propertyPath): array
	{
		if (!\preg_match('#
				^
				(?:([\w\\\]+)::)?
				([\w\\\]++(?:->\w++)*+)
				$
		#x', $propertyPath, $matches)) {
			throw new InvalidArgumentException('Unsupported condition format.');
		}

		\array_shift($matches); // whole expression

		/** @var string $source */
		$source = \array_shift($matches);
		$tokens = \explode('->', \array_shift($matches));

		if ($source === '') {
			$source = null;
			if ($tokens[0] === 'this') {
				\trigger_error("Using 'this->' is deprecated; use property traversing directly without 'this->'.", E_USER_DEPRECATED);
				\array_shift($tokens);
			} elseif (\strpos($tokens[0], '\\') !== false) {
				$source = \array_shift($tokens);
				\trigger_error("Using STI class prefix '$source->' is deprecated; use with double-colon '$source::'.", E_USER_DEPRECATED);
			}
		}

		if ($source !== null && !\is_subclass_of($source, IEntity::class)) {
			throw new InvalidArgumentException("Property expression '$propertyPath' uses class '$source' that is not " . IEntity::class . '.');
		}

		return [$tokens, $source];
	}
}
