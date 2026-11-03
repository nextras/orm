<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Aggregations\NumericAggregator;
use function max;


class MaxAggregateFunction extends BaseNumericAggregateFunction
{
	public function __construct()
	{
		parent::__construct(
			new NumericAggregator(
				arrayAggregation: static function (array $values) {
					if ($values === []) return null;
					return max($values);
				},
				dbalAggregationFunction: 'MAX',
			),
		);
	}
}
