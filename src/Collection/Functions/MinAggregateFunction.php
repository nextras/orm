<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Functions;


class MinAggregateFunction extends BaseAggregateFunction
{
	public function __construct()
	{
		parent::__construct('MIN');
	}


	protected function calculateAggregation(array $values)
	{
		return \min($values);
	}
}
