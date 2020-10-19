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
	/** @var PropertyMetadata */
	protected $metadata;


	public function __construct(PropertyMetadata $metadata)
	{
		$this->metadata = $metadata;
	}


	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		$key = $parent->getRawValue($this->metadata->path ?? $this->metadata->name);
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
