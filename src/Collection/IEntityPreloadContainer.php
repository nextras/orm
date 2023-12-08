<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


interface IEntityPreloadContainer
{
	/**
	 * Returns array of $property values for preloading.
	 * @return list<mixed>
	 */
	public function getPreloadValues(string $property): array;
}
