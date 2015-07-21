<?php

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
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\MemberAccessException;
use Nextras\Orm\Repository\IRepository;


class DbalCollection implements ICollection
{
	/** @var IRelationshipMapper */
	protected $relationshipMapper;

	/** @var IEntity */
	protected $relationshipParent;

	/** @var Iterator */
	protected $fetchIterator;

	/** @var IRepository */
	protected $repository;

	/** @var Connection */
	protected $connection;

	/** @var QueryBuilder */
	protected $queryBuilder;

	/** @var QueryBuilderHelper */
	protected $parser;

	/** @var array|NULL */
	protected $result;

	/** @var int */
	protected $resultCount;

	/** @var bool */
	protected $distinct = FALSE;


	public function __construct(IRepository $repository, Connection $connection, QueryBuilder $queryBuilder)
	{
		$this->repository = $repository;
		$this->connection = $connection;
		$this->queryBuilder = $queryBuilder;
	}


	public function getBy(array $where)
	{
		return $this->findBy($where)->limitBy(1)->fetch();
	}


	public function findBy(array $where)
	{
		$collection = clone $this;
		$parser = $collection->getParser();

		foreach ($where as $column => $value) {
			$parser->processWhereExpression($column, $value, $collection->queryBuilder, $collection->distinct);
		}

		return $collection;
	}


	public function orderBy($column, $direction = ICollection::ASC)
	{
		$collection = clone $this;
		$parser = $collection->getParser();

		if (is_array($column)) {
			foreach ($column as $col => $direction) {
				$parser->processOrderByExpression($col, $direction, $collection->queryBuilder);
			}
		} else {
			$parser->processOrderByExpression($column, $direction, $collection->queryBuilder);
		}

		return $collection;
	}


	public function resetOrderBy()
	{
		$collection = clone $this;
		$collection->queryBuilder->orderBy(NULL);
		return $collection;
	}


	public function limitBy($limit, $offset = NULL)
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

		return NULL;
	}


	public function fetchAll()
	{
		return iterator_to_array($this->getIterator());
	}


	public function fetchPairs($key = NULL, $value = NULL)
	{
		return FetchPairsHelper::process($this->getIterator(), $key, $value);
	}


	/** @deprecated */
	public function toCollection($resetOrderBy = FALSE)
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
		return $this->getEntityIterator($this->relationshipParent);
	}


	public function getEntityIterator(IEntity $parent = NULL)
	{
		if ($this->relationshipMapper) {
			return $this->relationshipMapper->getIterator($parent, $this);
		}

		if ($this->result === NULL) {
			$this->execute();
		}

		return new EntityIterator($this->result);
	}


	public function count()
	{
		return iterator_count($this->getIterator());
	}


	public function countStored()
	{
		return $this->getEntityCount($this->relationshipParent);
	}


	public function getEntityCount(IEntity $parent = NULL)
	{
		if ($this->relationshipMapper) {
			return $this->relationshipMapper->getIteratorCount($parent, $this);
		}

		return $this->getIteratorCount();
	}


	public function setRelationshipMapping(IRelationshipMapper $mapper = NULL, IEntity $parent = NULL)
	{
		$this->relationshipMapper = $mapper;
		$this->relationshipParent = $parent;
		return $this;
	}


	public function getRelationshipMapper()
	{
		return $this->relationshipMapper;
	}


	public function __clone()
	{
		$this->queryBuilder = clone $this->queryBuilder;
		$this->result = NULL;
		$this->resultCount = NULL;
		$this->fetchIterator = NULL;
	}


	/**
	 * @internal
	 * @return QueryBuilder
	 */
	public function getQueryBuilder()
	{
		return $this->queryBuilder;
	}


	protected function getIteratorCount()
	{
		if ($this->resultCount === NULL) {
			if ($this->queryBuilder->hasLimitOffsetClause()) {
				$sql = 'SELECT COUNT(*) FROM (' . $this->queryBuilder->getQuerySql() . ') temp';
				$args = $this->queryBuilder->getQueryParameters();

			} else {
				$builder = clone $this->queryBuilder;
				$builder->select('COUNT(*)');
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

		if (!$this->distinct) {
			$builder->select("[$table.*]");
		} else {
			$builder->select("DISTINCT [$table.*]");
		}

		$result = $this->connection->queryArgs(
			$builder->getQuerySql(),
			$builder->getQueryParameters()
		);

		$this->result = [];
		while ($data = $result->fetch()) {
			$this->result[] = $this->repository->hydrateEntity($data->toArray());
		}
	}


	protected function getParser()
	{
		if ($this->parser === NULL) {
			$this->parser = new QueryBuilderHelper($this->repository->getModel(), $this->repository->getMapper());
		}

		return $this->parser;
	}

}
