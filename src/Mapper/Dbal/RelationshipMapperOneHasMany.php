<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


use Iterator;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Row;
use Nextras\Orm\Collection\DbalCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Collection\MultiEntityIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\IRelationshipMapper;
use function array_merge;
use function array_unique;
use function array_unshift;
use function assert;
use function count;
use function implode;
use function json_encode;
use function md5;
use function sort;


class RelationshipMapperOneHasMany implements IRelationshipMapper
{
	protected PropertyRelationshipMetadata $metadataRelationship;
	protected string $joinStorageKey;

	/** @var array<string, MultiEntityIterator> */
	protected array $cacheEntityIterators;

	/** @var array<string, array<int>> */
	protected array $cacheCounts;


	/**
	 * @param DbalMapper<IEntity> $targetMapper
	 */
	public function __construct(
		protected readonly  IConnection $connection,
		protected readonly DbalMapper $targetMapper,
		protected readonly PropertyMetadata $metadata,
	)
	{
		assert($metadata->relationship !== null);
		assert($metadata->relationship->property !== null);
		$this->metadataRelationship = $metadata->relationship;
		$this->joinStorageKey = $targetMapper->getConventions()
			->convertEntityToStorageKey($metadata->relationship->property);
	}


	public function clearCache(): void
	{
		$this->cacheEntityIterators = [];
		$this->cacheCounts = [];
	}


	// ==== ITERATOR ===================================================================================================


	/**
	 * @param ICollection<IEntity> $collection
	 */
	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		assert($collection instanceof DbalCollection);
		$iterator = clone $this->execute($collection, $parent);
		$iterator->setDataIndex($parent->getValue('id'));
		return $iterator;
	}


	/**
	 * @param DbalCollection<IEntity> $collection
	 */
	protected function execute(DbalCollection $collection, IEntity $parent): MultiEntityIterator
	{
		$preloadContainer = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadContainer !== null ? $preloadContainer->getPreloadValues('id') : [$parent->getValue('id')];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		$data = &$this->cacheEntityIterators[$cacheKey];

		if ($data !== null) {
			return $data;
		}

		$builder = $collection->getQueryBuilder();
		if ($builder->hasLimitOffsetClause() && count($values) > 1) {
			$data = $this->fetchByTwoPassStrategy($builder, $values);
		} else {
			$data = $this->fetchByOnePassStrategy($builder, $values);
		}

		return $data;
	}


	/**
	 * @param list<mixed> $values
	 */
	protected function fetchByOnePassStrategy(QueryBuilder $builder, array $values): MultiEntityIterator
	{
		$builder = clone $builder;
		$builder->andWhere('%column IN %any', "{$builder->getFromAlias()}.{$this->joinStorageKey}", $values);

		$result = $this->connection->queryByQueryBuilder($builder);
		$entities = [];

		$property = $this->metadataRelationship->property;
		assert($property !== null);

		while (($data = $result->fetch())) {
			$entity = $this->targetMapper->hydrateEntity($data->toArray());
			if ($entity !== null) { // entity may have been deleted
				$entities[$entity->getRawValue($property)][] = $entity;
			}
		}

		return new MultiEntityIterator($entities);
	}


	/**
	 * @param list<mixed> $values
	 */
	protected function fetchByTwoPassStrategy(QueryBuilder $builder, array $values): MultiEntityIterator
	{
		$builder = clone $builder;
		$targetPrimaryKey = array_map(function ($key): string {
			return $this->targetMapper->getConventions()->convertEntityToStorageKey($key);
		}, $this->metadataRelationship->entityMetadata->getPrimaryKey());
		$isComposite = count($targetPrimaryKey) !== 1;

		foreach (array_unique(array_merge($targetPrimaryKey, [$this->joinStorageKey])) as $key) {
			$builder->addSelect("%column", "{$builder->getFromAlias()}.$key");
		}

		$result = $this->processMultiResult($builder, $values);

		$map = $ids = [];
		if ($isComposite) {
			foreach ($result as $row) {
				$id = [];
				foreach ($targetPrimaryKey as $key) {
					$id["{$builder->getFromAlias()}.$key"] = $row->{$key};
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

		sort($values); // make ids sorted deterministically
		if ($isComposite) {
			$builder = $this->targetMapper->builder();
			$builder->andWhere('%multiOr', $ids);

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
				$entities[$entity->getRawValue($this->metadataRelationship->property)][] = $entity;
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
		return $counts[$id] ?? 0;
	}


	/**
	 * @param DbalCollection<IEntity> $collection
	 * @return array<int|string, int>
	 */
	protected function executeCounts(DbalCollection $collection, IEntity $parent): array
	{
		$preloadContainer = $parent instanceof IEntityHasPreloadContainer ? $parent->getPreloadContainer() : null;
		$values = $preloadContainer !== null ? $preloadContainer->getPreloadValues('id') : [$parent->getValue('id')];
		$builder = $collection->getQueryBuilder();

		$cacheKey = $this->calculateCacheKey($builder, $values);
		$data = &$this->cacheCounts[$cacheKey];

		if ($data !== null) {
			return $data;
		}

		/** @noinspection PhpUnnecessaryLocalVariableInspection */
		$data = $this->fetchCounts($builder, $values);
		return $data;
	}


	/**
	 * @param list<mixed> $values
	 * @return array<int|string, int>
	 */
	private function fetchCounts(QueryBuilder $builder, array $values): array
	{
		$sourceTable = $builder->getFromAlias();

		$builder = clone $builder;

		if ($builder->hasLimitOffsetClause()) {
			$builder->select('%column', "{$sourceTable}.{$this->joinStorageKey}");
			$result = $this->processMultiCountResult($builder, $values);

		} else {
			$targetStoragePrimaryKeys = $this->targetMapper->getConventions()->getStoragePrimaryKey();
			$targetColumn = null;
			foreach ($targetStoragePrimaryKeys as $targetStoragePrimaryKey) {
				if ($targetStoragePrimaryKey === $this->joinStorageKey) {
					continue;
				}
				$targetColumn = "$sourceTable.$targetStoragePrimaryKey";
				break;
			}

			if ($targetColumn === null) {
				throw new InvalidStateException('Unable to detect column for count query.');
			}

			$builder->addSelect('%column AS [count]', $targetColumn);
			$builder->andWhere('%column IN %any', "{$sourceTable}.{$this->joinStorageKey}", $values);
			$builder->orderBy(null);

			$boxingBuilder = $this->connection->createQueryBuilder();
			$boxingBuilder->addSelect('%column, COUNT(DISTINCT [count]) as [count]', $this->joinStorageKey);
			$boxingBuilder->groupBy('%column', $this->joinStorageKey);

			$args = $builder->getQueryParameters();
			array_unshift($args, $builder->getQuerySql());
			$boxingBuilder->from('(%ex)', 'temp', $args);

			$result = $this->connection->queryByQueryBuilder($boxingBuilder);
		}

		$counts = [];
		foreach ($result as $row) {
			$counts[$row->{$this->joinStorageKey}] = $row->count;
		}
		return $counts;
	}


	/**
	 * @param list<mixed> $values
	 * @return iterable<Row>
	 */
	protected function processMultiResult(QueryBuilder $builder, array $values): iterable
	{
		if ($this->connection->getPlatform()->getName() === 'mssql') {
			$result = [];
			foreach ($values as $primaryValue) {
				$builderPart = clone $builder;
				$builderPart->andWhere("%column = %any", $this->joinStorageKey, $primaryValue);
				$result = array_merge($this->connection->queryByQueryBuilder($builderPart)->fetchAll(), $result);
			}
			return $result;

		} else {
			$sqls = $args = [];
			foreach ($values as $primaryValue) {
				$builderPart = clone $builder;
				$builderPart->andWhere("%column = %any", $this->joinStorageKey, $primaryValue);
				$sqls[] = $builderPart->getQuerySql();
				$args = array_merge($args, $builderPart->getQueryParameters());
			}

			$query = '(' . implode(') UNION ALL (', $sqls) . ')';
			return $this->connection->queryArgs($query, $args);
		}
	}


	/**
	 * @param list<mixed> $values
	 * @return iterable<Row>
	 */
	protected function processMultiCountResult(QueryBuilder $builder, array $values): iterable
	{
		$sourceTable = $builder->getFromAlias();

		if ($this->connection->getPlatform()->getName() === 'mssql') {
			$result = [];
			foreach ($values as $value) {
				$builderPart = clone $builder;
				$builderPart->andWhere('%column = %any', "{$sourceTable}.{$this->joinStorageKey}", $value);
				$result = array_merge($this->connection->queryArgs(
					"SELECT %any AS %column, COUNT(*) AS [count] FROM (" . $builderPart->getQuerySql() . ') [temp]',
					array_merge([$value, $this->joinStorageKey], $builderPart->getQueryParameters())
				)->fetchAll(), $result);
			}
			return $result;

		} else {
			$sqls = [];
			$args = [];
			$builder->orderBy(null);
			foreach ($values as $value) {
				$builderPart = clone $builder;
				$builderPart->andWhere('%column = %any', "{$sourceTable}.{$this->joinStorageKey}", $value);
				$sqls[] = "SELECT %any AS %column, COUNT(*) AS [count] FROM (" . $builderPart->getQuerySql() . ') [temp]';
				$args[] = $value;
				$args[] = $this->joinStorageKey;
				$args = array_merge($args, $builderPart->getQueryParameters());
			}

			$sql = '(' . implode(') UNION ALL (', $sqls) . ')';
			$result = $this->connection->queryArgs($sql, $args);
			return $result;
		}
	}


	/**
	 * @param list<mixed> $values
	 */
	protected function calculateCacheKey(QueryBuilder $builder, array $values): string
	{
		return md5($builder->getQuerySql() . json_encode($builder->getQueryParameters()) . json_encode($values));
	}
}
