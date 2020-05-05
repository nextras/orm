<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use function count;


class CountAggregateFunction extends BaseAggregateFunction
{
	public function __construct()
	{
		parent::__construct('COUNT');
	}


	protected function calculateAggregation(array $values)
	{
		return count($values);
	}
}
