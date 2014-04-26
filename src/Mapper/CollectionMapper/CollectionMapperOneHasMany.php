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
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\NotImplementedException;
use Nextras\Orm\Repository\IRepository;


/**
 * OneHasManyCollectionMapper for Nette Framework.
 */
class CollectionMapperOneHasMany extends Object implements ICollectionMapperHasMany
{
	/** @var Context */
	protected $databaseContext;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IMapper */
	protected $targetMapper;

	/** @var IRepository */
	protected $targetRepository;

	/** @var ICollectionMapper */
	protected $defaultCollectionMapper;

	/** @var string */
	protected $joinStorageKey;

	/** @var array */
	protected $targetStoragePrimaryKey;

	/** @var IEntityIterator[] */
	protected $cacheEntityIterator;


	public function __construct(Context $databaseContext, IMapper $targetMapper, ICollectionMapper $defaultCollectionMapper, PropertyMetadata $metadata)
	{
		$this->databaseContext = $databaseContext;
		$this->targetMapper = $targetMapper;
		$this->targetRepository = $targetMapper->getRepository();
		$this->defaultCollectionMapper = $defaultCollectionMapper;
		$this->metadata = $metadata;

		$this->joinStorageKey = $targetMapper->getStorageReflection()->convertEntityToStorageKey($this->metadata->args[1]);
		$this->targetStoragePrimaryKey = $targetMapper->getStorageReflection()->getStoragePrimaryKey();
	}


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
		$builder = $collectionMapper->getSqlBuilder();
		$cacheKey = $builder->buildSelectQuery() . ($preloadIterator ? spl_object_hash($preloadIterator) : '');

		$data = & $this->cacheEntityIterator[$cacheKey];
		if ($data) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadPrimaryValues() : [$parent->getValue('id')];

		if ($builder->getLimit() || $builder->getOffset() || $builder->getOrder()) {
			$data = $this->fetchByTwoPassStrategy(clone $builder, $values);
		} else {
			$data = $this->fetchByOnePassStrategy(clone $builder, $values);
		}

		return $data;
	}


	private function fetchByOnePassStrategy(SqlBuilder $builder, array $values)
	{
		$builder->addWhere($this->joinStorageKey, $values);
		return $this->queryAndFetchEntities($builder->buildSelectQuery(), $builder->getParameters());
	}


	private function fetchByTwoPassStrategy(SqlBuilder $builder, array $values)
	{
		foreach (array_unique(array_merge([$this->joinStorageKey], $this->targetStoragePrimaryKey)) as $key) {
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
		$result = $this->databaseContext->queryArgs($query, $args);
		$entities = [];
		foreach ($result->fetchAll() as $data) {
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
