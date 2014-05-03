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
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\Collection\IEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\NotImplementedException;
use Nextras\Orm\Repository\IRepository;


/**
 * OneHasManyCollectionMapper for Nette Framework.
 */
class CollectionMapperOneHasMany extends Object implements ICollectionMapperHasMany
{
	/** @var Context */
	protected $context;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IMapper */
	protected $targetMapper;

	/** @var IRepository */
	protected $targetRepository;

	/** @var ICollection */
	protected $defaultCollection;

	/** @var string */
	protected $joinStorageKey;

	/** @var IEntityIterator[] */
	protected $cacheEntityIterator;

	/** @var int[] */
	protected $cacheCounts;


	public function __construct(Context $context, IMapper $targetMapper, ICollection $defaultCollection, PropertyMetadata $metadata)
	{
		$this->context = $context;
		$this->targetMapper = $targetMapper;
		$this->targetRepository = $targetMapper->getRepository();
		$this->defaultCollection = $defaultCollection;
		$this->metadata = $metadata;

		$this->joinStorageKey = $targetMapper->getStorageReflection()->convertEntityToStorageKey($this->metadata->args[1]);
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
		if ($builder->getLimit() || $builder->getOffset() || $builder->getOrder()) {
			$data = $this->fetchByTwoPassStrategy($builder, $values);
		} else {
			$data = $this->fetchByOnePassStrategy($builder, $values);
		}

		return $data;
	}


	protected function fetchByOnePassStrategy(SqlBuilder $builder, array $values)
	{
		$builder = clone $builder;
		$builder->addWhere($this->joinStorageKey, $values);
		return $this->queryAndFetchEntities($builder->buildSelectQuery(), $builder->getParameters());
	}


	protected function fetchByTwoPassStrategy(SqlBuilder $builder, array $values)
	{
		$targetStoragePrimaryKey = $this->targetMapper->getStorageReflection()->getStoragePrimaryKey();
		foreach (array_unique(array_merge([$this->joinStorageKey], $targetStoragePrimaryKey)) as $key) {
			$builder->addSelect($key);
		}

		$sqls = $args = [];
		foreach ($values as $primaryValue) {
			$builderPart = clone $builder;
			$builderPart->addWhere($this->joinStorageKey, $primaryValue);

			$sqls[] = $builderPart->buildSelectQuery();
			$args = array_merge($args, $builderPart->getParameters());
		}

		$query = '(' . implode(') UNION (', $sqls) . ')';
		return $this->queryAndFetchEntities($query, $args);
	}


	private function queryAndFetchEntities($query, $args)
	{
		$result = $this->context->queryArgs($query, $args);
		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetRepository->hydrateEntity((array) $data);
			$entities[$entity->getForeignKey($this->metadata->args[1])][] = $entity;
		}

		return new EntityIterator($entities);
	}


	public function getIteratorCount(IEntity $parent, ICollectionMapper $collectionMapper = NULL)
	{
		throw new NotImplementedException();
		//$builder = clone $this->builder;
		//$builder->addSelect('COUNT(*)');
		//dump($this->getTargetStorageReflection()->convertEntityToStorageKey($this->metadata->args[1]));
		//$builder->setGroup()
		//return $this->connection->fetchField($builder->buildSelectQuery(), $builder->getParameters());
	}

}
