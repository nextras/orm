<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Memory;

use ArrayIterator;
use Iterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\NotSupportedException;


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
		$key = $parent->getRawValue($this->metadata->name);
		return new ArrayIterator([
			$key ? $collection->getBy(['id' => $key]) : null,
		]);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		throw new NotSupportedException();
	}
}
