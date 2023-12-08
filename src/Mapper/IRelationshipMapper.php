<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


use Iterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


interface IRelationshipMapper
{
	/**
	 * Returns iterator.
	 * @param ICollection<IEntity> $collection
	 * @return Iterator<int, IEntity>
	 */
	public function getIterator(IEntity $parent, ICollection $collection): Iterator;


	/**
	 * Returns iterator's counts.
	 * @param ICollection<IEntity> $collection
	 */
	public function getIteratorCount(IEntity $parent, ICollection $collection): int;


	/**
	 * Clears relationship cache for entity preloading.
	 */
	public function clearCache(): void;
}
