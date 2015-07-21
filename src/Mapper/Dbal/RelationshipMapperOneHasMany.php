<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Object;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Collection\IEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;


/**
 * OneHasMany relationship mapper for Nextras\Dbal.
 */
class RelationshipMapperOneHasMany extends Object implements IRelationshipMapper
{
	/** @var Connection */
	protected $connection;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var DbalMapper */
	protected $targetMapper;

	/** @var IRepository */
	protected $targetRepository;

	/** @var string */
	protected $joinStorageKey;

	/** @var IEntityIterator[] */
	protected $cacheEntityIterator;

	/** @var int[] */
	protected $cacheCounts;


	public function __construct(Connection $connection, DbalMapper $targetMapper, PropertyMetadata $metadata)
	{
		$this->connection = $connection;
		$this->targetMapper = $targetMapper;
		$this->targetRepository = $targetMapper->getRepository();
		$this->metadata = $metadata;

		$this->joinStorageKey = $targetMapper->getStorageReflection()->convertEntityToStorageKey($this->metadata->relationship->property);
	}


	public function isStoredInEntity()
	{
		return FALSE;
	}


	// ==== ITERATOR ===================================================================================================


	public function getIterator(IEntity $parent, ICollection $collection)
	{
		/** @var IEntityIterator $iterator */
		$iterator = $this->execute($collection, $parent);
		$iterator->setDataIndex($parent->id);
		return $iterator;
	}


	protected function execute(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$cacheKey = $this->calculateCacheKey($builder, $preloadIterator, $parent);

		$data = & $this->cacheEntityIterator[$cacheKey];
		if ($data !== NULL) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->id];
		if ($builder->hasLimitOffsetClause() && count($values) > 1) {
			$data = $this->fetchByTwoPassStrategy($builder, $values);
		} else {
			$data = $this->fetchByOnePassStrategy($builder, stripos($cacheKey, 'JOIN') !== FALSE, $values);
		}

		return $data;
	}


	protected function fetchByOnePassStrategy(QueryBuilder $builder, $hasJoin, array $values)
	{
		$builder = clone $builder;
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . $builder->getFromAlias() . '.*');
		$builder->andWhere("{$builder->getFromAlias()}.{$this->joinStorageKey} IN %any", $values);
		return $this->queryAndFetchEntities($builder->getQuerySql(), $builder->getQueryParameters());
	}


	protected function fetchByTwoPassStrategy(QueryBuilder $builder, array $values)
	{
		$builder = clone $builder;
		$targetPrimaryKey = $this->targetMapper->getStorageReflection()->getStoragePrimaryKey();
		$isComposite = count($targetPrimaryKey) !== 1;

		foreach (array_unique(array_merge($targetPrimaryKey, [$this->joinStorageKey])) as $key) {
			$builder->addSelect($key);
		}

		$sqls = $args = [];
		foreach ($values as $primaryValue) {
			$builderPart = clone $builder;
			$builderPart->andWhere("$this->joinStorageKey = %any", $primaryValue);

			$sqls[] = $builderPart->getQuerySQL();
			$args = array_merge($args, $builderPart->getQueryParameters());
		}

		$query = '(' . implode(') UNION ALL (', $sqls) . ')';
		$result = $this->connection->queryArgs($query, $args);

		$map = $ids = [];
		if ($isComposite) {
			foreach ($result as $row) {
				$id = [];
				foreach ($targetPrimaryKey as $key) {
					$id[] = $row->{$key};
				}

				$ids[] = $id;
				$map[$row->{$this->joinStorageKey}][] = implode(',', $id);
			}

		} else {
			$targetPrimaryKey = $targetPrimaryKey[0];
			foreach ($result as $row) {
				$ids[] = $row->{$targetPrimaryKey};
				$map[$row->{$this->joinStorageKey}][] = $row->{$targetPrimaryKey};
			}
		}

		if (count($ids) === 0) {
			return new EntityIterator([]);
		}

		if ($isComposite) {
			$builder = $this->targetMapper->builder();
			$builder->andWhere('%column[] IN %any', $targetPrimaryKey, $ids);

			$entitiesResult = [];
			$collection = $this->targetMapper->toCollection($builder);
			foreach ($collection as $entity) {
				$entitiesResult[implode(',', $entity->getValue('id'))] = $entity;
			}
		} else {
			$entitiesResult = $this->targetRepository->findBy([$targetPrimaryKey => $ids])->fetchPairs($targetPrimaryKey, NULL);
		}

		$entities = [];
		foreach ($map as $joiningStorageKey => $primaryValues) {
			foreach ($primaryValues as $primaryValue) {
				$entity = $entitiesResult[$primaryValue];
				$entities[$entity->getRawValue($this->metadata->relationship->property)][] = $entity;
			}
		}

		return new EntityIterator($entities);
	}


	private function queryAndFetchEntities($query, $args)
	{
		$result = $this->connection->queryArgs($query, $args);
		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetRepository->hydrateEntity($data->toArray());
			$entities[$entity->getRawValue($this->metadata->relationship->property)][] = $entity;
		}

		return new EntityIterator($entities);
	}


	// ==== ITERATOR COUNT =============================================================================================


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		$counts = $this->executeCounts($collection, $parent);
		return isset($counts[$parent->id]) ? $counts[$parent->id] : 0;
	}


	protected function executeCounts(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$cacheKey = $this->calculateCacheKey($builder, $preloadIterator, $parent);

		$data = & $this->cacheCounts[$cacheKey];
		if ($data !== NULL) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->id];
		$data = $this->fetchCounts($builder, $values);
		return $data;
	}


	private function fetchCounts(QueryBuilder $builder, array $values)
	{
		$targetStoragePrimaryKey = $this->targetMapper->getStorageReflection()->getStoragePrimaryKey()[0];

		$sourceTable = $builder->getFromAlias();

		$builder = clone $builder;
		$builder->addSelect("{$sourceTable}.{$this->joinStorageKey}");
		$builder->orderBy(NULL);

		if ($builder->hasLimitOffsetClause()) {
			$sqls = [];
			$args = [];
			foreach ($values as $value) {
				$build = clone $builder;
				$build->andWhere("{$sourceTable}.{$this->joinStorageKey} = %any", $value);
				$sqls[] = "SELECT {$value} as {$this->joinStorageKey}, COUNT(*) AS count FROM (" . $build->getQuerySql() . ') temp';
				$args = array_merge($args, $build->getQueryParameters());
			}

			$sql = '(' . implode(') UNION ALL (', $sqls) . ')';
			$result = $this->connection->queryArgs($sql, $args);

		} else {
			$builder->addSelect("COUNT({$sourceTable}.{$targetStoragePrimaryKey}) AS count");
			$builder->andWhere("{$sourceTable}.{$this->joinStorageKey} IN %any", $values);
			$builder->groupBy("{$sourceTable}.{$this->joinStorageKey}");

			$result = $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());
		}

		$counts = [];
		foreach ($result as $row) {
			$counts[$row->{$this->joinStorageKey}] = $row->count;
		}
		return $counts;
	}


	protected function calculateCacheKey(QueryBuilder $builder, $preloadIterator, $parent)
	{
		return md5($builder->getQuerySQL() . json_encode($builder->getQueryParameters())
			. ($preloadIterator ? spl_object_hash($preloadIterator) : json_encode($parent->id)));
	}

}
