<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


/**
 * @template T
 */
interface IArrayAggregator
{
	/**
	 * @param array<T> $values
	 * @return T
	 */
	function aggregateValues(array $values);
}
