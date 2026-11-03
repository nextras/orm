<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


interface IEntityPreloadContainer
{
	/**
	 * Returns array of $property values for preloading.
	 * @return list<mixed>
	 */
	public function getPreloadValues(string $property): array;

	/**
	 * Returns a cached hash of getPreloadValues($property), computed once per preload batch and property.
	 */
	public function getPreloadValuesHash(string $property): string;
}
