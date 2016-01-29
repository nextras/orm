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
use Nextras\Orm\Collection\EntityContainer;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\NotSupportedException;
use Nextras\Orm\Repository\IRepository;


class RelationshipMapperManyHasOne extends Object implements IRelationshipMapper
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
		$preloadContainer = $parent->getPreloadContainer();
		$values = $preloadContainer ? $preloadContainer->getPreloadValues($this->metadata->name) : [$parent->getRawValue($this->metadata->name)];
		$cacheKey = $this->calculateCacheKey($builder, $values);

		$data = & $this->cacheEntityContainers[$cacheKey];
		if ($data) {
			return $data;
		}

		$data = $this->fetch(clone $builder, stripos($cacheKey, 'JOIN') !== false, $values);
		return $data;
	}


	protected function fetch(QueryBuilder $builder, $hasJoin, array $values)
	{
		$values = array_values(array_unique(array_filter($values, function ($value) {
			return $value !== null;
		})));

		if (count($values) === 0) {
			return new EntityContainer([]);
		}

		$primaryKey = $this->targetRepository->getMapper()->getStorageReflection()->getStoragePrimaryKey()[0];
		$builder->andWhere('%column IN %any', $primaryKey, $values);
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . '%table.*', $builder->getFromAlias());
		$result = $this->connection->queryArgs($builder->getQuerySQL(), $builder->getQueryParameters());

		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetRepository->hydrateEntity($data->toArray());
			$entities[$entity->getValue('id')] = $entity;
		}

		return new EntityContainer($entities);
	}


	protected function calculateCacheKey(QueryBuilder $builder, array $values)
	{
		return md5($builder->getQuerySQL() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
