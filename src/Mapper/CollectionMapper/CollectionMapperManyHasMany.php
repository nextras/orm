<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\CollectionMapper;

use Nette\Database\Context;
use Nette\Database\Table\SqlBuilder;
use Nette\Object;
use Nextras\Orm\Entity\Collection\EntityIterator;
use Nextras\Orm\Entity\Collection\IEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Repository\IRepository;


/**
 * ManyHasManyCollectionMapper for Nette Framework.
 */
class CollectionMapperManyHasMany extends Object implements ICollectionMapperHasMany
{
	/** @var Context */
	protected $databaseContext;

	/** @var IMapper */
	protected $mapperTwo;

	/** @var IMapper */
	protected $mapperOne;

	/** @var ICollectionMapper */
	protected $defaultCollectionMapper;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IEntityIterator[] */
	protected $cacheEntityIterator;

	/** @var int[] */
	protected $cacheCounts;

	/** @var string */
	protected $primaryKeyFrom;

	/** @var string */
	protected $primaryKeyTo;

	/** @var IRepository */
	protected $targetRepository;


	public function __construct(Context $databaseContext, IMapper $mapperOne, IMapper $mapperTwo, ICollectionMapper $defaultCollectionMapper, PropertyMetadata $metadata)
	{
		$this->databaseContext = $databaseContext;
		$this->mapperOne = $mapperOne;
		$this->mapperTwo = $mapperTwo;
		$this->metadata  = $metadata;
		$this->defaultCollectionMapper = $defaultCollectionMapper;

		$keys = $this->mapperOne->getStorageReflection()->getManyHasManyStoragePrimaryKeys($this->mapperTwo);
		if ($this->metadata->args[2]) { // primary
			$this->targetRepository = $this->mapperTwo->getRepository();
			list($this->primaryKeyFrom, $this->primaryKeyTo) = $keys;
		} else {
			$this->targetRepository = $this->mapperOne->getRepository();
			list($this->primaryKeyTo, $this->primaryKeyFrom) = $keys;
		}
	}


	// ==== ITERATOR ===================================================================================================


	public function getIterator(IEntity $parent, ICollectionMapper $collectionMapper = NULL)
	{
		/** @var IEntityIterator $iterator */
		$iterator = $this->execute($collectionMapper ?: $this->defaultCollectionMapper, $parent);
		$iterator->setDataIndex($parent->id);
		return $iterator;
	}


	protected function execute(CollectionMapper $collectionMapper, IEntity $parent)
	{
		$preloadIterator = $parent->getPreloadContainer();
		$builder  = $collectionMapper->getSqlBuilder();
		$cacheKey = $builder->buildSelectQuery() . ($preloadIterator ? spl_object_hash($preloadIterator) : '');

		$data = & $this->cacheEntityIterator[$cacheKey];
		if ($data) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadPrimaryValues() : [$parent->getValue('id')];
		$data = $this->fetchByTwoPassStrategy(clone $builder, $values);
		return $data;
	}


	private function fetchByTwoPassStrategy(SqlBuilder $builder, array $values)
	{
		$builder->addWhere($this->primaryKeyFrom, $values);
		$result = $this->databaseContext->queryArgs($builder->buildSelectQuery(), $builder->getParameters());

		$values = [];
		foreach ($result->fetchAll() as $row) {
			$values[$row->{$this->primaryKeyTo}] = NULL;
		}

		if (count($values) === 0) {
			return new EntityIterator([]);
		}

		$entitiesResult = $this->targetRepository->findById(array_keys($values));
		$entitiesResult->getIterator();

		$entities = [];
		foreach ($result->fetchAll() as $row) {
			$entities[$row->{$this->primaryKeyFrom}][] = $this->targetRepository->getById($row->{$this->primaryKeyTo});
		}

		return new EntityIterator($entities);
	}


	// ==== ITERATOR COUNT =============================================================================================


	public function getIteratorCount(IEntity $parent, ICollectionMapper $collectionMapper = NULL)
	{
		$counts = $this->executeCounts($collectionMapper ?: $this->defaultCollectionMapper, $parent);
		$id     = $parent->getValue('id');
		return isset($counts[$id]) ? $counts[$id] : 0;
	}


	protected function executeCounts(CollectionMapper $collectionMapper, IEntity $parent)
	{
		$preloadIterator = $parent->getPreloadContainer();
		$builder  = $collectionMapper->getSqlBuilder();
		$cacheKey = $builder->buildSelectQuery() . ($preloadIterator ? spl_object_hash($preloadIterator) : '');

		$data = & $this->cacheCounts[$cacheKey];
		if ($data) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadPrimaryValues() : [$parent->getValue('id')];
		$data = $this->fetchCounts(clone $builder, $values);
		return $data;
	}


	private function fetchCounts(SqlBuilder $builder, array $values)
	{
		$property = $builder->reflection->getProperty('tableName');
		$property->setAccessible(TRUE);
		$tableName = $property->getValue($builder);

		$builder->addSelect("{$tableName}.{$this->primaryKeyFrom}");
		$builder->addSelect("COUNT({$this->primaryKeyFrom}.{$this->primaryKeyFrom}) AS count");
		$builder->addWhere("{$tableName}.{$this->primaryKeyFrom}", $values);
		$builder->setGroup("{$tableName}.{$this->primaryKeyFrom}");
		$result = $this->databaseContext->queryArgs($builder->buildSelectQuery(), $builder->getParameters());

		$counts = [];
		foreach ($result->fetchAll() as $row) {
			$counts[$row->{$this->primaryKeyFrom}] = $row['count'];
		}
		return $counts;
	}


	// ==== OTHERS =====================================================================================================


	public function add(array $add)
	{
		$list = $this->buildList($add);
		$this->databaseContext->query($this->builder->buildInsertQuery(), $list);
	}


	public function remove(array $add)
	{
		$list = $this->buildList($add);
		$builder = clone $this->builder;
		$builder->addWhere(array_keys(reset($list)), $list);
		$this->databaseContext->queryArgs($builder->buildDeleteQuery(), $builder->getParameters());
	}


	protected function buildList(array $entries)
	{
		if (!$this->metadata->args[2]) {
			throw new LogicException('ManyHasMany relationship have to be persited on primary mapper.');
		}

		$list = [];
		$pId  = $this->parent->id;
		$key1 = $this->mapperOne->getStorageReflection()->getStoragePrimaryKey()[0];
		$key2 = $this->mapperTwo->getStorageReflection()->getStoragePrimaryKey()[0];

		foreach ($entries as $id) {
			$list[] = [
				$key1 => $pId,
				$key2 => $id
			];
		}

		return $list;
	}


}
