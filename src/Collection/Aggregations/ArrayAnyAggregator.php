<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


/**
 * @implements IArrayAggregator<bool>
 */
class ArrayAnyAggregator implements IArrayAggregator
{
	public function aggregate(array $values): bool
	{
		foreach ($values as $value) {
			if ($value) {
				return true;
			}
		}
		return false;
	}
}
