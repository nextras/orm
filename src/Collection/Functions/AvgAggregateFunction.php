<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Functions;

use function array_sum;
use function count;


class AvgAggregateFunction extends BaseAggregateFunction
{
	public function __construct()
	{
		parent::__construct('AVG');
	}


	protected function calculateAggregation(array $values)
	{
		return array_sum($values) / count($values);
	}
}
