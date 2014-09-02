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
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Collection\EntityContainer;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\NotSupportedException;
use Nextras\Orm\LogicException;


/**
 * ManyHasOne relationship mapper for Nette\Database.
 */
class RelationshipMapperHasOne extends Object implements IRelationshipMapper
{
	/** @var Context */
	protected $context;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IRepository */
	protected $targetRepository;

	/** @var EntityContainer[] */
	protected $cacheEntityContainers;


	public function __construct(Context $context, IMapper $targetMapper, PropertyMetadata $metadata)
	{
		$this->context = $context;
		$this->targetRepository = $targetMapper->getRepository();
		$this->metadata = $metadata;
	}


	public function getIterator(IEntity $parent, ICollection $collection)
	{
		$container = $this->execute($collection, $parent);
		return [$container->getEntity($parent->getForeignKey($this->metadata->name))];
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		throw new NotSupportedException();
	}


	protected function execute(ICollection $collection, IEntity $parent)
	{
		$collectionMapper = $collection->getCollectionMapper();
		if (!$collectionMapper instanceof CollectionMapper) {
			throw new LogicException();
		}

		$builder = $collectionMapper->getSqlBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$cacheKey = $builder->buildSelectQuery() . ($preloadIterator ? spl_object_hash($preloadIterator) : '');

		$data = & $this->cacheEntityContainers[$cacheKey];
		if ($data) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadValues($this->metadata->name) : [$parent->getValue($this->metadata->name)];
		$data = $this->fetch(clone $builder, stripos($cacheKey, 'JOIN') !== FALSE, $values);
		return $data;
	}


	protected function fetch(SqlBuilder $builder, $hasJoin, array $values)
	{
		$primaryKey = $this->targetRepository->getMapper()->getStorageReflection()->getStoragePrimaryKey()[0];
		$builder->addWhere($primaryKey, $values);
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . $builder->getTableName() . '.*');
		$result = $this->context->queryArgs($builder->buildSelectQuery(), $builder->getParameters());

		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetRepository->hydrateEntity((array) $data);
			$entities[$entity->id] = $entity;
		}

		return new EntityContainer($entities);
	}

}
