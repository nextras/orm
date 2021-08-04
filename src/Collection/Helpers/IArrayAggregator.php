<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Helpers;


/**
 * @template T
 */
interface IArrayAggregator
{
	/**
	 * @param array<T> $values
	 * @return T
	 */
	function aggregate(array $values);
}
