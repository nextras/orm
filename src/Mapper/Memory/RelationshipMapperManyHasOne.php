<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Memory;

use Nette\SmartObject;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\NotSupportedException;


class RelationshipMapperManyHasOne implements IRelationshipMapper
{
	use SmartObject;

	/** @var PropertyMetadata */
	protected $metadata;


	public function __construct(PropertyMetadata $metadata)
	{
		$this->metadata = $metadata;
	}


	public function getIterator(IEntity $parent, ICollection $collection)
	{
		$key = $parent->getRawValue($this->metadata->name);
		return [$key ? $collection->getBy(['id' => $key]) : null];
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		throw new NotSupportedException();
	}
}
