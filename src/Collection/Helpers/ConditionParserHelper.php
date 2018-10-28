<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nextras\Orm\InvalidArgumentException;


class ConditionParserHelper
{
	/** @const operators */
	public const OPERATOR_EQUAL = '=';
	public const OPERATOR_NOT_EQUAL = '!=';
	public const OPERATOR_GREATER = '>';
	public const OPERATOR_EQUAL_OR_GREATER = '>=';
	public const OPERATOR_SMALLER = '<';
	public const OPERATOR_EQUAL_OR_SMALLER = '<=';


	public static function parsePropertyOperator(string $condition): array
	{
		if (!preg_match('#^(.+?)(!=|<=|>=|=|>|<)?$#', $condition, $matches)) {
			return [$condition, self::OPERATOR_EQUAL];
		} else {
			return [$matches[1], $matches[2] ?? self::OPERATOR_EQUAL];
		}
	}


	public static function parsePropertyExpr(string $propertyPath): array
	{
		if (!preg_match('#^([\w\\\]+(?:->\w++)*+)\z#', $propertyPath, $matches)) {
			throw new InvalidArgumentException('Unsupported condition format.');
		}

		$source = null;
		$tokens = explode('->', $matches[1]);
		if (count($tokens) > 1) {
			$source = array_shift($tokens);
			$source = $source === 'this' ? null : $source;
		}

		return [$tokens, $source];
	}
}
