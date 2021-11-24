<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


/**
 * @implements IArrayAggregator<bool>
 */
class ArrayNoneAggregator implements IArrayAggregator
{
	public function aggregate(array $values): bool
	{
		foreach ($values as $value) {
			if ($value) {
				return false;
			}
		}
		return true;
	}
}
