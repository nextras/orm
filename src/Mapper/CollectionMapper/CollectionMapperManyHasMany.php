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
use Nextras\Orm\Entity\Collection\Collection;
use Nextras\Orm\Entity\Collection\EntityIterator;
use Nextras\Orm\Entity\Collection\ICollection;
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
	protected $context;

	/** @var IMapper */
	protected $mapperTwo;

	/** @var IMapper */
	protected $mapperOne;

	/** @var ICollection */
	protected $defaultCollection;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IEntityIterator[] */
	protected $cacheEntityIterator;

	/** @var int[] */
	protected $cacheCounts;

	/** @var string */
	protected $joinTable;

	/** @var string */
	protected $primaryKeyFrom;

	/** @var string */
	protected $primaryKeyTo;

	/** @var IRepository */
	protected $targetRepository;


	public function __construct(Context $context, IMapper $mapperOne, IMapper $mapperTwo, ICollection $defaultCollection, PropertyMetadata $metadata)
	{
		$this->context   = $context;
		$this->mapperOne = $mapperOne;
		$this->mapperTwo = $mapperTwo;
		$this->metadata  = $metadata;
		$this->defaultCollection = $defaultCollection;

		$parameters = $mapperOne->getManyHasManyParameters($mapperTwo);
		$this->joinTable = $parameters[0];

		if ($this->metadata->args[2]) { // primary
			$this->targetRepository = $this->mapperTwo->getRepository();
			list($this->primaryKeyFrom, $this->primaryKeyTo) = $parameters[1];
		} else {
			$this->targetRepository = $this->mapperOne->getRepository();
			list($this->primaryKeyTo, $this->primaryKeyFrom) = $parameters[1];
		}
	}


	// ==== ITERATOR ===================================================================================================


	public function getIterator(IEntity $parent, ICollection $collection = NULL)
	{
		/** @var IEntityIterator $iterator */
		$iterator = $this->execute($collection ?: $this->defaultCollection, $parent);
		$iterator->setDataIndex($parent->id);
		return $iterator;
	}


	protected function execute(ICollection $collection, IEntity $parent)
	{
		$collectionMapper = $collection->getMapper();
		if (!$collectionMapper instanceof CollectionMapper) {
			throw new LogicException();
		}

		$builder = $collectionMapper->getSqlBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$cacheKey = $builder->buildSelectQuery() . ($preloadIterator ? spl_object_hash($preloadIterator) : '');

		$data = & $this->cacheEntityIterator[$cacheKey];
		if ($data) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadPrimaryValues() : [$parent->id];
		$data = $this->fetchByTwoPassStrategy($builder, $values);
		return $data;
	}


	private function fetchByTwoPassStrategy(SqlBuilder $builder, array $values)
	{
		$builder = clone $builder;
		$builder->addWhere(":{$this->joinTable}($this->primaryKeyTo).$this->primaryKeyFrom", $values);
		$builder->addSelect(":{$this->joinTable}($this->primaryKeyTo).$this->primaryKeyTo");
		$builder->addSelect(":{$this->joinTable}($this->primaryKeyTo).$this->primaryKeyFrom");

		$result = $this->context->queryArgs($builder->buildSelectQuery(), $builder->getParameters());

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


	public function getIteratorCount(IEntity $parent, ICollection $collection = NULL)
	{
		$counts = $this->executeCounts($collection ?: $this->defaultCollection, $parent);
		return isset($counts[$parent->id]) ? $counts[$parent->id] : 0;
	}


	protected function executeCounts(ICollection $collection, IEntity $parent)
	{
		$collectionMapper = $collection->getMapper();
		if (!$collectionMapper instanceof CollectionMapper) {
			throw new LogicException();
		}

		$builder = $collectionMapper->getSqlBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$cacheKey = $builder->buildSelectQuery() . ($preloadIterator ? spl_object_hash($preloadIterator) : '');

		$data = & $this->cacheCounts[$cacheKey];
		if ($data) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadPrimaryValues() : [$parent->id];
		$data = $this->fetchCounts($builder, $values);
		return $data;
	}


	private function fetchCounts(SqlBuilder $builder, array $values)
	{
		$builder = clone $builder;
		$builder->addWhere(":{$this->joinTable}($this->primaryKeyTo).$this->primaryKeyFrom", $values);
		$builder->addSelect(":{$this->joinTable}($this->primaryKeyTo).$this->primaryKeyFrom");
		$builder->addSelect("COUNT(:{$this->joinTable}($this->primaryKeyTo).$this->primaryKeyTo) AS count");
		$builder->setGroup(":{$this->joinTable}($this->primaryKeyTo).$this->primaryKeyFrom");

		$result = $this->context->queryArgs($builder->buildSelectQuery(), $builder->getParameters());

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
		$this->context->query($this->builder->buildInsertQuery(), $list);
	}


	public function remove(array $add)
	{
		$list = $this->buildList($add);
		$builder = clone $this->builder;
		$builder->addWhere(array_keys(reset($list)), $list);
		$this->context->queryArgs($builder->buildDeleteQuery(), $builder->getParameters());
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
