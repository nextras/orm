<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use function min;


class MinAggregateFunction extends BaseAggregateFunction
{
	public function __construct()
	{
		parent::__construct('MIN');
	}


	protected function calculateAggregation(array $values)
	{
		return min($values);
	}
}
