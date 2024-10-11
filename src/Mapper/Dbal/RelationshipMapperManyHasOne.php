<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


use ArrayIterator;
use Iterator;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Mapper\IRelationshipMapper;


class RelationshipMapperManyHasOne implements IRelationshipMapper
{
	/** @var array<string, MultiEntityIterator> */
	protected array $cacheEntityIterators;


	/**
	 * @param DbalMapper<IEntity> $targetMapper
	 */
	public function __construct(
		protected readonly IConnection $connection,
		protected readonly DbalMapper $targetMapper,
		protected readonly PropertyMetadata $metadata,
	)
	{
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


	public function clearCache(): void
	{
	}


	/**
	 * @param DbalCollection<IEntity> $collection
	 */
	protected function execute(DbalCollection $collection, IEntity $parent): MultiEntityIterator
	{
		$preloadContainer = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadContainer !== null ? $preloadContainer->getPreloadValues($this->metadata->name) : [$parent->getRawValue($this->metadata->name)];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		$data = &$this->cacheEntityIterators[$cacheKey];

		if ($data !== null) {
			return $data;
		}

		/** @noinspection PhpUnnecessaryLocalVariableInspection */
		$data = $this->fetch(clone $builder, $values);
		return $data;
	}


	/**
	 * @param list<mixed> $values
	 */
	protected function fetch(QueryBuilder $builder, array $values): MultiEntityIterator
	{
		$values = array_values(array_unique(array_filter($values, function ($value): bool {
			return $value !== null;
		})));

		if (count($values) === 0) {
			return new MultiEntityIterator([]);
		}

		$conventions = $this->targetMapper->getConventions();
		$primaryKey = $conventions->getStoragePrimaryKey()[0];
		$builder->andWhere('%table.%column IN %any', $builder->getFromAlias(), $primaryKey, $values);
		$result = $this->connection->queryByQueryBuilder($builder);

		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetMapper->hydrateEntity($data->toArray());
			if ($entity !== null) { // entity may have been deleted
				$entities[$entity->getValue('id')] = [$entity];
			}
		}

		return new MultiEntityIterator($entities);
	}


	/**
	 * @param list<mixed> $values
	 */
	protected function calculateCacheKey(QueryBuilder $builder, array $values): string
	{
		return md5($builder->getQuerySql() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
