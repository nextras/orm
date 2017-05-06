<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Iterator;
use Nette\Object;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;


class RelationshipMapperOneHasMany extends Object implements IRelationshipMapper
{
	/** @var Connection */
	protected $connection;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var DbalMapper */
	protected $targetMapper;

	/** @var string */
	protected $joinStorageKey;

	/** @var MultiEntityIterator[] */
	protected $cacheEntityIterators;

	/** @var int[] */
	protected $cacheCounts;


	public function __construct(Connection $connection, DbalMapper $targetMapper, PropertyMetadata $metadata)
	{
		$this->connection = $connection;
		$this->targetMapper = $targetMapper;
		$this->metadata = $metadata;
		$this->joinStorageKey = $targetMapper->getStorageReflection()->convertEntityToStorageKey($this->metadata->relationship->property);
	}


	public function clearCache()
	{
		$this->cacheEntityIterators = [];
		$this->cacheCounts = [];
	}


	// ==== ITERATOR ===================================================================================================


	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		assert($collection instanceof DbalCollection);
		$iterator = clone $this->execute($collection, $parent);
		$iterator->setDataIndex($parent->getValue('id'));
		return $iterator;
	}


	protected function execute(DbalCollection $collection, IEntity $parent): MultiEntityIterator
	{
		$preloadContainer = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadContainer ? $preloadContainer->getPreloadValues('id') : [$parent->getValue('id')];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		$data = & $this->cacheEntityIterators[$cacheKey];

		if ($data !== null) {
			return $data;
		}

		$builder = $collection->getQueryBuilder();
		if ($builder->hasLimitOffsetClause() && count($values) > 1) {
			$data = $this->fetchByTwoPassStrategy($builder, $values);
		} else {
			$data = $this->fetchByOnePassStrategy($builder, stripos($builder->getQuerySql(), 'JOIN') !== false, $values);
		}

		return $data;
	}


	protected function fetchByOnePassStrategy(QueryBuilder $builder, $hasJoin, array $values): MultiEntityIterator
	{
		$builder = clone $builder;
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . '%table.*', $builder->getFromAlias());
		$builder->andWhere('%column IN %any', "{$builder->getFromAlias()}.{$this->joinStorageKey}", $values);

		$result = $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());
		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetMapper->hydrateEntity($data->toArray());
			$entities[$entity->getRawValue($this->metadata->relationship->property)][] = $entity;
		}

		return new MultiEntityIterator($entities);
	}


	protected function fetchByTwoPassStrategy(QueryBuilder $builder, array $values): MultiEntityIterator
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

			$sqls[] = $builderPart->getQuerySql();
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
			return new MultiEntityIterator([]);
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
			$entitiesResult = $this->targetMapper->findAll()->findBy(['id' => $ids])->fetchPairs('id', null);
		}

		$entities = [];
		foreach ($map as $joiningStorageKey => $primaryValues) {
			foreach ($primaryValues as $primaryValue) {
				$entity = $entitiesResult[$primaryValue];
				$entities[$entity->getRawValue($this->metadata->relationship->property)][] = $entity;
			}
		}

		return new MultiEntityIterator($entities);
	}


	// ==== ITERATOR COUNT =============================================================================================


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		assert($collection instanceof DbalCollection);
		$counts = $this->executeCounts($collection, $parent);
		$id = $parent->getValue('id');
		return isset($counts[$id]) ? $counts[$id] : 0;
	}


	protected function executeCounts(DbalCollection $collection, IEntity $parent)
	{
		$preloadContainer = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadContainer ? $preloadContainer->getPreloadValues('id') : [$parent->getValue('id')];
		$builder = $collection->getQueryBuilder();

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


	protected function calculateCacheKey(QueryBuilder $builder, array $values): string
	{
		return md5($builder->getQuerySql() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
