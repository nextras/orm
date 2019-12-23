<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


/**
 * @internal
 * @phpstan-template E of \Nextras\Orm\Entity\IEntity
 * @phpstan-extends ArrayCollection<E>
 */
class MutableArrayCollection extends ArrayCollection
{
	/**
	 * @phpstan-param list<E> $data
	 * @return static
	 */
	public function withData(array $data): ICollection
	{
		$collection = clone $this;
		$collection->data = $data;
		return $collection;
	}
}
