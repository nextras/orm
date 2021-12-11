<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


interface IAggregator
{
	/**
	 * @return literal-string
	 */
	public function getAggregateKey(): string;
}
