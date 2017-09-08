<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use ArrayIterator;
use Iterator;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\NotSupportedException;


class RelationshipMapperManyHasOne implements IRelationshipMapper
{
	/** @var Connection */
	protected $connection;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var Entity[][][] */
	protected $cacheEntityGroups;

	/** @var DbalMapper */
	private $targetMapper;


	public function __construct(Connection $connection, DbalMapper $targetMapper, PropertyMetadata $metadata)
	{
		$this->connection = $connection;
		$this->metadata = $metadata;
		$this->targetMapper = $targetMapper;
	}


	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		assert($collection instanceof DbalCollection);
		$data = $this->execute($collection, $parent);
		return new ArrayIterator($data[$parent->getRawValue($this->metadata->name)] ?? []);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		throw new NotSupportedException();
	}


	protected function execute(DbalCollection $collection, IEntity $parent): array
	{
		$preloadContainer = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadContainer ? $preloadContainer->getPreloadValues($this->metadata->name) : [$parent->getRawValue($this->metadata->name)];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		$data = & $this->cacheEntityGroups[$cacheKey];

		if ($data) {
			return $data;
		}

		$data = $this->fetch(clone $builder, stripos($builder->getQuerySql(), 'JOIN') !== false, $values);
		return $data;
	}


	protected function fetch(QueryBuilder $builder, $hasJoin, array $values): array
	{
		$values = array_values(array_unique(array_filter($values, function ($value) {
			return $value !== null;
		})));

		if (count($values) === 0) {
			return [];
		}

		$storageReflection = $this->targetMapper->getStorageReflection();
		$primaryKey = $storageReflection->getStoragePrimaryKey()[0];
		$builder->andWhere('%column IN %any', $primaryKey, $values);
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . '%table.*', $builder->getFromAlias());
		$result = $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());

		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetMapper->hydrateEntity($data->toArray());
			if ($entity !== null) { // entity may have been deleted
				$entities[$entity->getValue('id')] = [$entity];
			}
		}

		return $entities;
	}


	protected function calculateCacheKey(QueryBuilder $builder, array $values): string
	{
		return md5($builder->getQuerySql() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
