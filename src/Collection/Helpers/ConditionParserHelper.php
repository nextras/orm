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
	const OPERATOR_EQUAL = '=';
	const OPERATOR_NOT_EQUAL = '!=';
	const OPERATOR_GREATER = '>';
	const OPERATOR_EQUAL_OR_GREATER = '>=';
	const OPERATOR_SMALLER = '<';
	const OPERATOR_EQUAL_OR_SMALLER = '<=';


	public static function parsePropertyExprWithOperator(string $condition): array
	{
		if (!preg_match('#^([\w\\\]+(?:->\w+)*)(!|!=|<=|>=|=|>|<)?$#', $condition, $matches)) {
			throw new InvalidArgumentException('Unsupported condition format.');
		}

		$source = null;
		$tokens = explode('->', $matches[1]);
		if (count($tokens) > 1) {
			$source = array_shift($tokens);
			$source = $source === 'this' ? null : $source;
		}

		return [
			$tokens,
			isset($matches[2]) ? ($matches[2] === '!' ? '!=' : $matches[2]) : '=',
			$source,
		];
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
