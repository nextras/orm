<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Collection;

use Nextras\Orm\InvalidArgumentException;


class ConditionParser
{
	/** @const operators */
	const OPERATOR_EQUAL = '=';
	const OPERATOR_NOT_EQUAL = '!=';
	const OPERATOR_GREATER = '>';
	const OPERATOR_EQUAL_OR_GREATER = '=>';
	const OPERATOR_SMALLER = '<';
	const OPERATOR_EQUAL_OR_SMALLER = '=<';


	public static function parseCondition($condition)
	{
		if (!preg_match('#^(this((?:->\w+)+)|\w+)(!|!=|<=|>=|=|>|<)?$#', $condition, $matches)) {
			throw new InvalidArgumentException('Unsupported condition format.');
		}

		return [
			!empty($matches[2]) ? explode('->', substr($matches[2], 2)) : [$matches[1]],
			isset($matches[3]) ? ($matches[3] === '!' ? '!=' : $matches[3]) : '=',
		];
	}

}
