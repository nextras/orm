<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


/**
 * @internal
 */
class MutableArrayCollection extends ArrayCollection
{
	/**
	 * @phpstan-param list<\Nextras\Orm\Entity\IEntity> $data
	 * @return static
	 */
	public function withData(array $data): ICollection
	{
		$collection = clone $this;
		$collection->data = $data;
		return $collection;
	}
}
