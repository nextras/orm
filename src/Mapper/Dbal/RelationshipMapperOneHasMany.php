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


	// ==== ITERATOR ===================================================================================================


	public function getIterator(IEntity $parent, ICollection $collection)
	{
		/** @var IEntityIterator $iterator */
		$iterator = clone $this->execute($collection, $parent);
		$iterator->setDataIndex($parent->getValue('id'));
		return $iterator;
	}


	protected function execute(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->getValue('id')];
		$cacheKey = $this->calculateCacheKey($builder, $values);

		$data = & $this->cacheEntityIterator[$cacheKey];
		if ($data !== null) {
			return $data;
		}

		if ($builder->hasLimitOffsetClause() && count($values) > 1) {
			$data = $this->fetchByTwoPassStrategy($builder, $values);
		} else {
			$data = $this->fetchByOnePassStrategy($builder, stripos($cacheKey, 'JOIN') !== false, $values);
		}

		return $data;
	}


	protected function fetchByOnePassStrategy(QueryBuilder $builder, $hasJoin, array $values)
	{
		$builder = clone $builder;
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . '%table.*', $builder->getFromAlias());
		$builder->andWhere('%column IN %any', "{$builder->getFromAlias()}.{$this->joinStorageKey}", $values);
		return $this->queryAndFetchEntities($builder->getQuerySql(), $builder->getQueryParameters());
	}


	protected function fetchByTwoPassStrategy(QueryBuilder $builder, array $values)
	{
		$builder = clone $builder;
		$targetPrimaryKey = $this->targetMapper->getStorageReflection()->getStoragePrimaryKey();
		$isComposite = count($targetPrimaryKey) !== 1;

		foreach (array_unique(array_merge($targetPrimaryKey, [$this->joinStorageKey])) as $key) {
			$builder->addSelect("[$key]");
		}

		$sqls = $args = [];
		foreach ($values as $primaryValue) {
			$builderPart = clone $builder;
			$builderPart->andWhere("%column = %any", $this->joinStorageKey, $primaryValue);

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
			$builder->andWhere('(%column[]) IN %any', $targetPrimaryKey, $ids);

			$entitiesResult = [];
			$collection = $this->targetMapper->toCollection($builder);
			foreach ($collection as $entity) {
				$entitiesResult[implode(',', $entity->getValue('id'))] = $entity;
			}
		} else {
			$entitiesResult = $this->targetRepository->findBy(['id' => $ids])->fetchPairs('id', null);
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
		$id = $parent->getValue('id');
		return isset($counts[$id]) ? $counts[$id] : 0;
	}


	protected function executeCounts(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->getValue('id')];
		$cacheKey = $this->calculateCacheKey($builder, $values);

		$data = & $this->cacheCounts[$cacheKey];
		if ($data !== null) {
			return $data;
		}

		$data = $this->fetchCounts($builder, $values);
		return $data;
	}


	private function fetchCounts(QueryBuilder $builder, array $values)
	{
		$targetStoragePrimaryKey = $this->targetMapper->getStorageReflection()->getStoragePrimaryKey()[0];
		$sourceTable = $builder->getFromAlias();

		$builder = clone $builder;
		$builder->addSelect('%column', "{$sourceTable}.{$this->joinStorageKey}");
		$builder->orderBy(null);

		if ($builder->hasLimitOffsetClause()) {
			$sqls = [];
			$args = [];
			foreach ($values as $value) {
				$build = clone $builder;
				$build->andWhere('%column = %any', "{$sourceTable}.{$this->joinStorageKey}", $value);
				$sqls[] = "SELECT %any AS %column, COUNT(*) AS [count] FROM (" . $build->getQuerySql() . ') [temp]';
				$args[] = $value;
				$args[] = $this->joinStorageKey;
				$args = array_merge($args, $build->getQueryParameters());
			}

			$sql = '(' . implode(') UNION ALL (', $sqls) . ')';
			$result = $this->connection->queryArgs($sql, $args);

		} else {
			$builder->addSelect('COUNT(%column) AS [count]', "{$sourceTable}.{$targetStoragePrimaryKey}");
			$builder->andWhere('%column IN %any', "{$sourceTable}.{$this->joinStorageKey}", $values);
			$builder->groupBy('%column', "{$sourceTable}.{$this->joinStorageKey}");
			$result = $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());
		}

		$counts = [];
		foreach ($result as $row) {
			$counts[$row->{$this->joinStorageKey}] = $row->count;
		}
		return $counts;
	}


	protected function calculateCacheKey(QueryBuilder $builder, array $values)
	{
		return md5($builder->getQuerySQL() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
