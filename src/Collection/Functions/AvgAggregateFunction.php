<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Aggregations\NumericAggregator;
use function array_sum;
use function count;


class AvgAggregateFunction extends BaseNumericAggregateFunction
{
	public function __construct()
	{
		parent::__construct(
			new NumericAggregator(
				arrayAggregation: static function (array $values) {
					$count = count($values);
					if ($count === 0) return null;
					return array_sum($values) / $count;
				},
				dbalAggregationFunction: 'AVG',
			),
		);
	}
}
