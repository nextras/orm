<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use function count;
use function max;


class MaxAggregateFunction extends BaseAggregateFunction
{
	public function __construct()
	{
		parent::__construct('MAX');
	}


	protected function calculateAggregation(array $values)
	{
		if (count($values) === 0) {
			return null;
		}

		return max($values);
	}
}
