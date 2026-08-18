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
	#[\NoDiscard('Method returns a new collection instance, the original collection is not modified.')]
	public function withData(array $data): ICollection
	{
		$collection = clone $this;
		$collection->data = $data;
		return $collection;
	}
}
