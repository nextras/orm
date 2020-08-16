<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


use ArrayIterator;
use Iterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\NotSupportedException;


class RelationshipMapperOneHasOne extends RelationshipMapperOneHasMany
{
	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		$iterator = parent::getIterator($parent, $collection);
		return new ArrayIterator(
			$iterator->valid() ? [$iterator->current()] : []
		);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		throw new NotSupportedException();
	}
}
