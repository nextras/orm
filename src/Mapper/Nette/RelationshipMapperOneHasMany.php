<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Nette;

use Nette\Database\Context;
use Nette\Database\Table\SqlBuilder;
use Nette\Object;
use Nextras\Orm\Entity\Collection\EntityIterator;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\Collection\IEntityIterator;
use Nextras\Orm\Entity\Collection\IEntityPreloadContainer;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\RuntimeException;


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
		$cacheKey = $this->calculateCacheKey($builder, $preloadIterator);

		$data = & $this->cacheEntityIterator[$cacheKey];
		if ($data !== NULL) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->id];
		if (($builder->getLimit() || $builder->getOffset()) && count($values) > 1) {
			$data = $this->fetchByTwoPassStrategy($builder, $values);
		} else {
			$data = $this->fetchByOnePassStrategy($builder, $values);
		}

		return $data;
	}


	protected function fetchByOnePassStrategy(SqlBuilder $builder, array $values)
	{
		$builder = clone $builder;
		$builder->addWhere("{$builder->getTableName()}.{$this->joinStorageKey}", $values);
		return $this->queryAndFetchEntities($builder->buildSelectQuery(), $builder->getParameters());
	}


	protected function fetchByTwoPassStrategy(SqlBuilder $builder, array $values)
	{
		$builderOne = clone $builder;
		$targetKeys = $this->targetMapper->getStorageReflection()->getStoragePrimaryKey();

		$isComposite = count($targetKeys) !== 1;
		if ($isComposite) {
			if (count(array_intersect([$this->joinStorageKey], $targetKeys)) !== 1) {
				throw new RuntimeException('Composite primary key must consist of foreign key.');
			}
			$builderOne->addSelect($targetKeys[0]);
			$builderOne->addSelect($targetKeys[1]);
		} else {
			$targetKey = array_values(array_diff($targetKeys, [$this->joinStorageKey]))[0];
			$builderOne->addSelect($this->joinStorageKey);
			$builderOne->addSelect($targetKey);
		}

		$sqls = $args = [];
		foreach ($values as $primaryValue) {
			$builderPart = clone $builderOne;
			$builderPart->addWhere($this->joinStorageKey, $primaryValue);

			$sqls[] = $builderPart->buildSelectQuery();
			$args = array_merge($args, $builderPart->getParameters());
		}

		$query = '(' . implode(') UNION (', $sqls) . ')';
		$result = $this->context->queryArgs($query, $args);
		$builderTwo = new SqlBuilder($builder->getTableName(), $this->context);

		if ($isComposite) {
			$ids = [];
			foreach ($result->fetchAll() as $pair) $ids[] = (array) $pair;
			$builderTwo->addWhere($targetKeys, $ids);
		} else {
			$ids = [];
			foreach ($result->fetchAll() as $pair) $ids[] = $pair->{$targetKey};
			$builderTwo->addWhere($targetKey, $ids);
		}

		return $this->queryAndFetchEntities($builderTwo->buildSelectQuery(), $builderTwo->getParameters());
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
		$cacheKey = $this->calculateCacheKey($builder, $preloadIterator);

		$data = & $this->cacheCounts[$cacheKey];
		if ($data !== NULL) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadValues('id') : [$parent->id];
		$data = $this->fetchCounts($builder, $values);
		return $data;
	}


	private function fetchCounts(SqlBuilder $builder, array $values)
	{
		$targetStoragePrimaryKey = $this->targetMapper->getStorageReflection()->getStoragePrimaryKey()[0];

		$builder = clone $builder;
		$table = $builder->getTableName();

		$builder->addSelect("{$table}.{$this->joinStorageKey}");
		$builder->addSelect("COUNT({$table}.{$targetStoragePrimaryKey}) AS count");
		$builder->addWhere("{$table}.{$this->joinStorageKey}", $values);
		$builder->setGroup("{$table}.{$this->joinStorageKey}");

		$result = $this->context->queryArgs($builder->buildSelectQuery(), $builder->getParameters());

		$counts = [];
		foreach ($result->fetchAll() as $row) {
			$counts[$row->{$this->joinStorageKey}] = $row['count'];
		}
		return $counts;
	}


	protected function calculateCacheKey(SqlBuilder $builder, IEntityPreloadContainer $preloadIterator = NULL)
	{
		return $builder->buildSelectQuery() . json_encode($builder->getParameters()) . ($preloadIterator ? spl_object_hash($preloadIterator) : '');
	}

}
