<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Nette;

use Nette\Object;
use Nette\Database\Context;
use Nette\Database\Table\SqlBuilder;
use Nextras\Orm\Entity\Collection\EntityIterator;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\Collection\IEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;


/**
 * OneHasMany relationship mapper for Nette\Database.
 */
class RelationshipMapperOneHasMany extends Object implements IRelationshipMapper
{
	/** @var Context */
	protected $context;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IMapper */
	protected $targetMapper;

	/** @var IRepository */
	protected $targetRepository;

	/** @var string */
	protected $joinStorageKey;

	/** @var IEntityIterator[] */
	protected $cacheEntityIterator;

	/** @var int[] */
	protected $cacheCounts;


	public function __construct(Context $context, IMapper $targetMapper, PropertyMetadata $metadata)
	{
		$this->context = $context;
		$this->targetMapper = $targetMapper;
		$this->targetRepository = $targetMapper->getRepository();
		$this->metadata = $metadata;

		$this->joinStorageKey = $targetMapper->getStorageReflection()->convertEntityToStorageKey($this->metadata->relationshipProperty);
	}


	// ==== ITERATOR ===================================================================================================


	public function getIterator(IEntity $parent, ICollection $collection)
	{
		/** @var IEntityIterator $iterator */
		$iterator = $this->execute($collection, $parent);
		$iterator->setDataIndex($parent->id);
		return $iterator;
	}


	protected function execute(ICollection $collection, IEntity $parent)
	{
		$collectionMapper = $collection->getCollectionMapper();
		if (!$collectionMapper instanceof CollectionMapper) {
			throw new LogicException();
		}

		$builder = $collectionMapper->getSqlBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->id];
		$cacheKey = $this->calculateCacheKey($builder, $values);

		$data = & $this->cacheEntityIterator[$cacheKey];
		if ($data !== NULL) {
			return $data;
		}

		if (($builder->getLimit() || $builder->getOffset()) && count($values) > 1) {
			$data = $this->fetchByTwoPassStrategy($builder, $values);
		} else {
			$data = $this->fetchByOnePassStrategy($builder, stripos($cacheKey, 'JOIN') !== FALSE, $values);
		}

		return $data;
	}


	protected function fetchByOnePassStrategy(SqlBuilder $builder, $hasJoin, array $values)
	{
		$builder = clone $builder;
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . $builder->getTableName() . '.*');
		$builder->addWhere("{$builder->getTableName()}.{$this->joinStorageKey}", $values);
		return $this->queryAndFetchEntities($builder->buildSelectQuery(), $builder->getParameters());
	}


	protected function fetchByTwoPassStrategy(SqlBuilder $builder, array $values)
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
			$builderPart->addWhere($this->joinStorageKey, $primaryValue);

			$sqls[] = $builderPart->buildSelectQuery();
			$args = array_merge($args, $builderPart->getParameters());
		}

		$query = '(' . implode(') UNION ALL (', $sqls) . ')';
		$result = $this->context->queryArgs($query, $args);

		$map = $ids = [];
		if ($isComposite) {
			foreach ($result->fetchAll() as $row) {
				$id = [];
				foreach ($targetPrimaryKey as $key) {
					$id[] = $row->{$key};
				}

				$ids[] = $id;
				$map[$row->{$this->joinStorageKey}][] = implode(',', $id);
			}

		} else {
			$targetPrimaryKey = $targetPrimaryKey[0];
			foreach ($result->fetchAll() as $row) {
				$ids[] = $row->{$targetPrimaryKey};
				$map[$row->{$this->joinStorageKey}][] = $row->{$targetPrimaryKey};
			}
		}

		if (count($ids) === 0) {
			return new EntityIterator([]);
		}

		if ($isComposite) {
			$collectionMapper = $this->targetRepository->findAll()->getCollectionMapper();
			if (!$collectionMapper instanceof CollectionMapper) {
				throw new InvalidStateException();
			}

			$builder = $collectionMapper->getSqlBuilder();
			$builder->addWhere($targetPrimaryKey, $ids);
			$collectionMapper = new SqlBuilderCollectionMapper($this->targetRepository, $this->context, $builder);

			$entitiesResult = [];
			foreach ($collectionMapper->getIterator() as $entity) {
				$entitiesResult[implode(',', $entity->getValue('id'))] = $entity;
			}
		} else {
			$entitiesResult = $this->targetRepository->findBy([$targetPrimaryKey => $ids])->fetchPairs($targetPrimaryKey, NULL);
		}

		$entities = [];
		foreach ($map as $joiningStorageKey => $primaryValues) {
			foreach ($primaryValues as $primaryValue) {
				$entity = $entitiesResult[$primaryValue];
				$entities[$entity->getForeignKey($this->metadata->relationshipProperty)][] = $entity;
			}
		}

		return new EntityIterator($entities);
	}


	private function queryAndFetchEntities($query, $args)
	{
		$result = $this->context->queryArgs($query, $args);
		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetRepository->hydrateEntity((array) $data);
			$entities[$entity->getForeignKey($this->metadata->relationshipProperty)][] = $entity;
		}

		return new EntityIterator($entities);
	}


	// ==== ITERATOR COUNT =============================================================================================


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		$counts = $this->executeCounts($collection, $parent);
		return isset($counts[$parent->id]) ? $counts[$parent->id] : 0;
	}


	protected function executeCounts(ICollection $collection, IEntity $parent)
	{
		$collectionMapper = $collection->getCollectionMapper();
		if (!$collectionMapper instanceof CollectionMapper) {
			throw new LogicException();
		}

		$builder = $collectionMapper->getSqlBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->id];
		$cacheKey = $this->calculateCacheKey($builder, $values);

		$data = & $this->cacheCounts[$cacheKey];
		if ($data !== NULL) {
			return $data;
		}

		$data = $this->fetchCounts($builder, $values);
		return $data;
	}


	private function fetchCounts(SqlBuilder $builder, array $values)
	{
		$targetStoragePrimaryKey = $this->targetMapper->getStorageReflection()->getStoragePrimaryKey()[0];
		$table = $builder->getTableName();

		$builder = clone $builder;
		$builder->addSelect("{$table}.{$this->joinStorageKey}");
		$builder->setOrder([], []);

		if ($builder->getLimit() || $builder->getOffset()) {
			$sqls = [];
			$args = [];
			foreach ($values as $value) {
				$build = clone $builder;
				$build->addWhere("{$table}.{$this->joinStorageKey}", $value);
				$sqls[] = "SELECT ? as {$this->joinStorageKey}, COUNT(*) AS count FROM (" . $build->buildSelectQuery() . ') temp';
				$args[] = $value;
				$args = array_merge($args, $build->getParameters());
			}

			$sql = '(' . implode(') UNION ALL (', $sqls) . ')';
			$result = $this->context->queryArgs($sql, $args)->fetchAll();

		} else {
			$builder->addSelect("COUNT({$table}.{$targetStoragePrimaryKey}) AS count");
			$builder->addWhere("{$table}.{$this->joinStorageKey}", $values);
			$builder->setGroup("{$table}.{$this->joinStorageKey}");

			$result = $this->context->queryArgs($builder->buildSelectQuery(), $builder->getParameters())->fetchAll();
		}

		$counts = [];
		foreach ($result as $row) {
			$counts[$row[$this->joinStorageKey]] = $row['count'];
		}
		return $counts;
	}


	protected function calculateCacheKey(SqlBuilder $builder, $values)
	{
		return md5($builder->buildSelectQuery() . json_encode($builder->getParameters()) . json_encode($values));
	}

}
