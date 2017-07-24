<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Iterator;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\StorageReflection\StorageReflection;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\MemberAccessException;
use Nextras\Orm\Repository\IRepository;


class DbalCollection implements ICollection
{
	/** @var array of callbacks with (\Traversable $entities) arugments */
	public $onEntityFetch = [];

	/** @var IRelationshipMapper|null */
	protected $relationshipMapper;

	/** @var IEntity|null */
	protected $relationshipParent;

	/** @var Iterator|null */
	protected $fetchIterator;

	/** @var IRepository */
	protected $repository;

	/** @var DbalMapper */
	protected $mapper;

	/** @var Connection */
	protected $connection;

	/** @var QueryBuilder */
	protected $queryBuilder;

	/** @var QueryBuilderHelper */
	protected $parser;

	/** @var array|null */
	protected $result;

	/** @var int|null */
	protected $resultCount;

	/** @var bool */
	protected $entityFetchEventTriggered = false;


	public function __construct(DbalMapper $mapper, Connection $connection, QueryBuilder $queryBuilder)
	{
		$this->mapper = $mapper;
		$this->connection = $connection;
		$this->queryBuilder = $queryBuilder;
		$this->parser = $mapper->getQueryBuilderHelper();
	}


	public function getBy(array $where)
	{
		return $this->findBy($where)->limitBy(1)->fetch();
	}


	public function findBy(array $where): ICollection
	{
		$collection = clone $this;
		$parser = $collection->parser;
		$builder = $collection->queryBuilder;

		$whereArg = $parser->processCallExpr($collection->queryBuilder, $where);
		$builder->andWhere('%ex', $whereArg); // TODO: do we really need %ex, can't we just use $builder->andWhere(...$whereArg)?

		return $collection;
	}


	public function orderBy($column, string $direction = ICollection::ASC): ICollection
	{
		$collection = clone $this;
		$parser = $collection->parser;
		$builder = $collection->queryBuilder;

		if (is_array($column)) {
			foreach ($column as $propertyPath => $direction) {
				$column = $parser->processPropertyExpr($builder, $propertyPath);
				$builder->addOrderBy($column . ($direction === ICollection::DESC ? ' DESC' : ''));
			}

		} else {
			$column = $parser->processPropertyExpr($builder, $column);
			$builder->addOrderBy($column . ($direction === ICollection::DESC ? ' DESC' : ''));
		}

		return $collection;
	}


	public function resetOrderBy(): ICollection
	{
		$collection = clone $this;
		$collection->queryBuilder->orderBy(null);
		return $collection;
	}


	public function limitBy(int $limit, int $offset = null): ICollection
	{
		$collection = clone $this;
		$collection->queryBuilder->limitBy($limit, $offset);
		return $collection;
	}


	public function fetch()
	{
		if (!$this->fetchIterator) {
			$this->fetchIterator = $this->getIterator();
		}

		if ($current = $this->fetchIterator->current()) {
			$this->fetchIterator->next();
			return $current;
		}

		return null;
	}


	public function fetchAll()
	{
		return iterator_to_array($this->getIterator());
	}


	public function fetchPairs(string $key = null, string $value = null): array
	{
		return FetchPairsHelper::process($this->getIterator(), $key, $value);
	}


	/** @deprecated */
	public function toCollection($resetOrderBy = false)
	{
		return $resetOrderBy ? $this->resetOrderBy() : clone $this;
	}


	public function __call($name, $args)
	{
		$class = get_class($this);
		throw new MemberAccessException("Call to undefined method $class::$name().");
	}


	public function getIterator()
	{
		if ($this->relationshipParent && $this->relationshipMapper) {
			$entityIterator = $this->relationshipMapper->getIterator($this->relationshipParent, $this);
		} else {
			if ($this->result === null) {
				$this->execute();
			}

			$entityIterator = new EntityIterator($this->result);
		}

		if (!$this->entityFetchEventTriggered) {
			foreach ($this->onEntityFetch as $entityFetchCallback) {
				$entityFetchCallback($entityIterator);
			}
			$entityIterator->rewind();
			$this->entityFetchEventTriggered = true;
		}

		return $entityIterator;
	}


	public function count()
	{
		return iterator_count($this->getIterator());
	}


	public function countStored(): int
	{
		if ($this->relationshipParent && $this->relationshipMapper) {
			return $this->relationshipMapper->getIteratorCount($this->relationshipParent, $this);
		}

		return $this->getIteratorCount();
	}


	public function setRelationshipMapper(IRelationshipMapper $mapper = null, IEntity $parent = null): ICollection
	{
		$this->relationshipMapper = $mapper;
		$this->relationshipParent = $parent;
		return $this;
	}


	public function getRelationshipMapper()
	{
		return $this->relationshipMapper;
	}


	public function setRelationshipParent(IEntity $parent): ICollection
	{
		$collection = clone $this;
		$collection->relationshipParent = $parent;
		return $collection;
	}


	public function subscribeOnEntityFetch(callable $callback)
	{
		$this->onEntityFetch[] = $callback;
	}


	public function __clone()
	{
		$this->queryBuilder = clone $this->queryBuilder;
		$this->result = null;
		$this->resultCount = null;
		$this->fetchIterator = null;
		$this->entityFetchEventTriggered = false;
	}


	/**
	 * @internal
	 * @return QueryBuilder
	 */
	public function getQueryBuilder()
	{
		return $this->queryBuilder;
	}


	protected function getIteratorCount(): int
	{
		if ($this->resultCount === null) {
			$builder = clone $this->queryBuilder;
			if ($builder->hasLimitOffsetClause()) {
				/** @var StorageReflection $reflection */
				$reflection = $this->mapper->getStorageReflection();
				$primary = $reflection->getStoragePrimaryKey();
				foreach ($primary as $column) {
					$builder->addSelect('%table.%column', $builder->getFromAlias(), $column);
				}
				$sql = 'SELECT COUNT(*) FROM (' . $builder->getQuerySql() . ') temp';
				$args = $builder->getQueryParameters();

			} else {
				$builder->select('COUNT(*)');
				$builder->orderBy(null);
				$sql = $builder->getQuerySql();
				$args = $builder->getQueryParameters();
			}

			$this->resultCount = $this->connection->queryArgs($sql, $args)->fetchField();
		}

		return $this->resultCount;
	}


	protected function execute()
	{
		$builder = clone $this->queryBuilder;

		$table = $builder->getFromAlias();
		$builder->select("[$table.*]");

		$result = $this->connection->queryArgs(
			$builder->getQuerySql(),
			$builder->getQueryParameters()
		);

		$this->result = [];
		while ($data = $result->fetch()) {
			$this->result[] = $this->mapper->hydrateEntity($data->toArray());
		}
	}
}
