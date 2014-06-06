<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Collection;

use Nextras\Orm\InvalidArgumentException;


class ConditionParser
{

	public static function parseCondition($condition)
	{
		if (!preg_match('#^(this((?:->\w+)+)|\w+)$#', $condition, $matches)) {
			throw new InvalidArgumentException('Unsupported condition format.');
		}

		return isset($matches[2]) ? explode('->', substr($matches[2], 2)) : [$matches[1]];
	}

}
