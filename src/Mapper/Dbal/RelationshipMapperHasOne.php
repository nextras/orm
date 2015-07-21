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
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Collection\EntityContainer;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\NotSupportedException;


/**
 * ManyHasOne relationship mapper for Nextras\Dbal.
 */
class RelationshipMapperHasOne extends Object implements IRelationshipMapper
{
	/** @var Connection */
	protected $connection;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IRepository */
	protected $targetRepository;

	/** @var EntityContainer[] */
	protected $cacheEntityContainers;


	public function __construct(Connection $connection, IMapper $targetMapper, PropertyMetadata $metadata)
	{
		$this->connection = $connection;
		$this->targetRepository = $targetMapper->getRepository();
		$this->metadata = $metadata;
	}


	public function getIterator(IEntity $parent, ICollection $collection)
	{
		$container = $this->execute($collection, $parent);
		return [$container->getEntity($parent->getRawValue($this->metadata->name))];
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		throw new NotSupportedException();
	}


	protected function execute(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$cacheKey = $this->calculateCacheKey($builder, $preloadIterator, $parent);

		$data = & $this->cacheEntityContainers[$cacheKey];
		if ($data) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadValues($this->metadata->name) : [$parent->getRawValue($this->metadata->name)];
		$data = $this->fetch(clone $builder, stripos($cacheKey, 'JOIN') !== FALSE, $values);
		return $data;
	}


	protected function fetch(QueryBuilder $builder, $hasJoin, array $values)
	{
		$values = array_values(array_unique(array_filter($values, function($value) {
			return $value !== NULL;
		})));

		if (count($values) === 0) {
			return new EntityContainer([]);
		}

		$primaryKey = $this->targetRepository->getMapper()->getStorageReflection()->getStoragePrimaryKey()[0];
		$builder->andWhere('%column IN %any', $primaryKey, $values);
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . $builder->getFromAlias() . '.*');
		$result = $this->connection->queryArgs($builder->getQuerySQL(), $builder->getQueryParameters());

		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetRepository->hydrateEntity($data->toArray());
			$entities[$entity->id] = $entity;
		}

		return new EntityContainer($entities);
	}


	protected function calculateCacheKey(QueryBuilder $builder, $preloadIterator, $parent)
	{
		return md5($builder->getQuerySQL() . json_encode($builder->getQueryParameters())
			. ($preloadIterator ? spl_object_hash($preloadIterator) : json_encode($parent->getRawValue($this->metadata->name))));
	}

}
