<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use function count;
use function min;


class MinAggregateFunction extends BaseAggregateFunction
{
	public function __construct()
	{
		parent::__construct('MIN');
	}


	protected function calculateAggregation(array $values)
	{
		if (count($values) === 0) {
			return null;
		}

		return min($values);
	}
}
