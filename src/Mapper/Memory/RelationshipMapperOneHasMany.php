<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Memory;

use Iterator;
use Nette\Object;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;


class RelationshipMapperOneHasMany extends Object implements IRelationshipMapper
{
	/** @var PropertyMetadata */
	protected $metadata;

	/** @var string */
	protected $joinStorageKey;


	public function __construct(ArrayMapper $targetMapper, PropertyMetadata $metadata)
	{
		$this->metadata = $metadata;
		$this->joinStorageKey = $targetMapper->getStorageReflection()->convertEntityToStorageKey($this->metadata->relationship->property);
	}


	public function clearCache()
	{
	}


	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		$data = $collection->findBy(["this->{$this->joinStorageKey}->id" => $parent->getValue('id')])->fetchAll();
		return new EntityIterator($data);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		return count($this->getIterator($parent, $collection));
	}
}
