<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Aggregations\NumericAggregator;
use function count;
use function min;


class MinAggregateFunction extends BaseNumericAggregateFunction
{
	public function __construct()
	{
		parent::__construct(
			new NumericAggregator(
				arrayAggregation: static function (array $values) {
					if (count($values) === 0) return null;
					return min($values);
				},
				dbalAggregationFunction: 'MIN',
			),
		);
	}
}
