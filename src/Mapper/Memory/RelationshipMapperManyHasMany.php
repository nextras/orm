<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Memory;

use Nette\Object;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapperManyHasMany;


/**
 * ManyHasMany relationship mapper for memory mapping.
 */
class RelationshipMapperManyHasMany extends Object implements IRelationshipMapperManyHasMany
{
	/** @var PropertyMetadata */
	protected $metadata;


	public function __construct(PropertyMetadata $metadata)
	{
		$this->metadata = $metadata;
	}


	public function getIterator(IEntity $parent, ICollection $collection)
	{
		$data = $collection->findById($parent->{$this->metadata->name}->getMemoryStorableValue())->fetchAll();
		return new EntityIterator($data);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		return count($this->getIterator($parent, $collection));
	}


	public function add(IEntity $parent, array $add)
	{
		// stored in injected value
	}


	public function remove(IEntity $parent, array $remove)
	{
		// stored in injected value
	}

}
