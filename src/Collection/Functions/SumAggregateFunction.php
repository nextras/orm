<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use function array_sum;


class SumAggregateFunction extends BaseAggregateFunction
{
	public function __construct()
	{
		parent::__construct('SUM');
	}


	protected function calculateAggregation(array $values)
	{
		return array_sum($values);
	}
}
