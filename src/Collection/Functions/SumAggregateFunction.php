<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Aggregations\NumericAggregator;


class SumAggregateFunction extends BaseNumericAggregateFunction
{
	public function __construct()
	{
		parent::__construct(
			new NumericAggregator(
				arrayAggregation: static fn (array $values) => array_sum($values),
				dbalAggregationFunction: 'SUM',
			),
		);
	}
}
