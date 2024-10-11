<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Memory;


use Countable;
use Iterator;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapperManyHasMany;
use function assert;


class RelationshipMapperManyHasMany implements IRelationshipMapperManyHasMany
{
	/** @var ArrayMapper<IEntity> */
	protected ArrayMapper $mapper;


	/**
	 * @param ArrayMapper<IEntity> $mapper
	 * @param ArrayMapper<IEntity> $sourceMapper
	 */
	public function __construct(
		ArrayMapper $mapper,
		ArrayMapper $sourceMapper,
		protected readonly PropertyMetadata $metadata,
	)
	{
		assert($metadata->relationship !== null);
		if ($metadata->relationship->isMain) {
			$this->mapper = $sourceMapper;
		} else {
			$this->mapper = $mapper;
		}
	}


	public function clearCache(): void
	{
	}


	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		assert($this->metadata->relationship !== null);
		if ($this->metadata->relationship->isMain) {
			$relationshipData = $this->mapper->getRelationshipDataStorage($this->metadata->name);
			$id = $parent->getValue('id');
			$ids = isset($relationshipData[$id]) ? array_keys($relationshipData[$id]) : [];
		} else {
			assert($this->metadata->relationship->property !== null);
			$ids = [];
			$parentId = $parent->getValue('id');
			$relationshipData = $this->mapper->getRelationshipDataStorage($this->metadata->relationship->property);
			foreach ($relationshipData as $id => $parentIds) {
				if (isset($parentIds[$parentId])) {
					$ids[] = $id;
				}
			}
		}

		$data = $collection->findBy(['id' => $ids])->fetchAll();
		return new EntityIterator($data);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		$iterator = $this->getIterator($parent, $collection);
		assert($iterator instanceof Countable);
		return count($iterator);
	}


	public function add(IEntity $parent, array $addIds): void
	{
		$id = $parent->getValue('id');
		$data = &$this->mapper->getRelationshipDataStorage($this->metadata->name);
		foreach ($addIds as $addId) {
			$data[$id][$addId] = true;
		}
	}


	public function remove(IEntity $parent, array $removeIds): void
	{
		$id = $parent->getValue('id');
		$data = &$this->mapper->getRelationshipDataStorage($this->metadata->name);
		foreach ($removeIds as $removeId) {
			unset($data[$id][$removeId]);
		}
	}
}
