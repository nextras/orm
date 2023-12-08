<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


/**
 * @internal
 * @template E of \Nextras\Orm\Entity\IEntity
 * @extends ArrayCollection<E>
 */
class MutableArrayCollection extends ArrayCollection
{
	/**
	 * @param list<E> $data
	 * @return static
	 */
	public function withData(array $data): ICollection
	{
		$collection = clone $this;
		$collection->data = $data;
		return $collection;
	}
}
