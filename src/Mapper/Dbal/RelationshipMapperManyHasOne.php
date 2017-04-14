<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use ArrayIterator;
use Iterator;
use Nette\Object;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
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

	/** @var MultiEntityIterator[] */
	protected $cacheEntityIterators;


	public function __construct(Connection $connection, IMapper $targetMapper, PropertyMetadata $metadata)
	{
		$this->connection = $connection;
		$this->targetRepository = $targetMapper->getRepository();
		$this->metadata = $metadata;
	}


	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		assert($collection instanceof DbalCollection);
		$container = $this->execute($collection, $parent);
		$container->setDataIndex($parent->getRawValue($this->metadata->name));
		return new ArrayIterator(iterator_to_array($container));
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		throw new NotSupportedException();
	}


	protected function execute(DbalCollection $collection, IEntity $parent): MultiEntityIterator
	{
		$preloadContainer = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadContainer ? $preloadContainer->getPreloadValues($this->metadata->name) : [$parent->getRawValue($this->metadata->name)];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		$data = & $this->cacheEntityIterators[$cacheKey];

		if ($data) {
			return $data;
		}

		$data = $this->fetch(clone $builder, stripos($builder->getQuerySql(), 'JOIN') !== false, $values);
		return $data;
	}


	protected function fetch(QueryBuilder $builder, $hasJoin, array $values): MultiEntityIterator
	{
		$values = array_values(array_unique(array_filter($values, function ($value) {
			return $value !== null;
		})));

		if (count($values) === 0) {
			return new MultiEntityIterator([]);
		}

		$primaryKey = $this->targetRepository->getMapper()->getStorageReflection()->getStoragePrimaryKey()[0];
		$builder->andWhere('%column IN %any', $primaryKey, $values);
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . '%table.*', $builder->getFromAlias());
		$result = $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());

		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetRepository->hydrateEntity($data->toArray());
			$entities[$entity->getValue('id')] = [$entity];
		}

		return new MultiEntityIterator($entities);
	}


	protected function calculateCacheKey(QueryBuilder $builder, array $values): string
	{
		return md5($builder->getQuerySql() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
