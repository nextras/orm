<?php declare(strict_types = 1);

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
		$count = count($values);
		if ($count === 0) {
			return null;
		}

		return array_sum($values) / $count;
	}
}
