<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Memory;

use Iterator;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;


class RelationshipMapperOneHasMany implements IRelationshipMapper
{
	/** @var PropertyMetadata */
	protected $metadata;

	/** @var string */
	protected $joinStorageKey;


	public function __construct(ArrayMapper $targetMapper, PropertyMetadata $metadata)
	{
		assert($metadata->relationship !== null);
		$this->metadata = $metadata;
		$this->joinStorageKey = $targetMapper->getStorageReflection()->convertEntityToStorageKey($metadata->relationship->property);
	}


	/**
	 * @return void
	 */
	public function clearCache()
	{
	}


	/**
	 * @return EntityIterator
	 */
	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		$className = $this->metadata->relationship->entityMetadata->className;
		$data = $collection->findBy(["$className->{$this->joinStorageKey}->id" => $parent->getValue('id')])->fetchAll();
		return new EntityIterator($data);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		return count($this->getIterator($parent, $collection));
	}
}
