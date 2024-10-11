<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Memory;


use Countable;
use Iterator;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;


class RelationshipMapperOneHasMany implements IRelationshipMapper
{
	protected string $joinStorageKey;


	/**
	 * @param ArrayMapper<IEntity> $targetMapper
	 */
	public function __construct(
		ArrayMapper $targetMapper,
		protected readonly PropertyMetadata $metadata,
	)
	{
		assert($metadata->relationship !== null);
		assert($metadata->relationship->property !== null);
		$this->joinStorageKey = $targetMapper->getConventions()
			->convertEntityToStorageKey($metadata->relationship->property);
	}


	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		assert($this->metadata->relationship !== null);
		$className = $this->metadata->relationship->entityMetadata->className;
		$data = $collection->findBy(["$className::{$this->joinStorageKey}->id" => $parent->getValue('id')])->fetchAll();
		return new EntityIterator($data);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		$iterator = $this->getIterator($parent, $collection);
		assert($iterator instanceof Countable);
		return count($iterator);
	}


	public function clearCache(): void
	{
	}
}
