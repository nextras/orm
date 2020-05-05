<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


interface IEntityPreloadContainer
{
	/**
	 * Returns array of $property values for preloading.
	 * @phpstan-return list<mixed>
	 */
	public function getPreloadValues(string $property): array;
}
