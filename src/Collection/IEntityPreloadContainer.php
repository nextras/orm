<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Nextras\Orm\Entity\Reflection\PropertyMetadata;


interface IEntityPreloadContainer
{
	/**
	 * Returns array of values in $propertyMetadata position for preloading.
	 * @phpstan-return list<mixed>
	 */
	public function getPreloadValues(PropertyMetadata $propertyMetadata): array;
}
