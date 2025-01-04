<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Memory;


use ArrayIterator;
use Iterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Mapper\IRelationshipMapper;


class RelationshipMapperManyHasOne implements IRelationshipMapper
{
	public function __construct(
		protected readonly PropertyMetadata $metadata,
	)
	{
	}


	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		$key = $parent->getRawValue($this->metadata->name);
		return new ArrayIterator(
			$key !== null ? [$collection->getByIdChecked($key)] : []
		);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		throw new NotSupportedException();
	}


	public function clearCache(): void
	{
	}
}
