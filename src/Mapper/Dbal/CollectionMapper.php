<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Object;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\EntityIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Mapper\ICollectionMapper;
use Nextras\Orm\Repository\IRepository;


/**
 * CollectionMapper for Nextras\Dbal.
 */
class CollectionMapper extends Object implements ICollectionMapper
{
	/** @var IRepository */
	protected $repository;

	/** @var Connection */
	protected $connection;

	/** @var QueryBuilder */
	protected $builder;

	/** @var QueryBuilderHelper */
	protected $parser;

	/** @var array */
	protected $result;

	/** @var int */
	protected $resultCount;

	/** @var bool */
	protected $distinct = FALSE;


	public function __construct(IRepository $repository, Connection $dbConnection, $tableName)
	{
		$this->repository = $repository;
		$this->connection = $dbConnection;

		$this->builder = new QueryBuilder($dbConnection->getDriver());
		$this->builder->from("[$tableName]", QueryBuilderHelper::getAlias($tableName));
	}


	public function addCondition($column, $value)
	{
		$this->release();
		$expression = $this->getParser()->parseJoinExpressionWithOperator($column, $value, $this->builder);
		$this->builder->andWhere("{$expression} %any", $value);

		if ($expression !== $column) {
			$this->distinct = TRUE;
		}

		return $this;
	}


	public function addOrderBy($expression, $direction = ICollection::ASC)
	{
		$this->release();
		$expression = $this->getParser()->parseJoinExpression($expression, $this->builder);
		$this->builder->addOrderBy($expression . ($direction === ICollection::DESC ? ' DESC' : ''));
		return $this;
	}


	public function resetOrderBy()
	{
		$this->release();
		$this->builder->orderBy(NULL);
		return $this;
	}


	public function limitBy($limit, $offset = NULL)
	{
		$this->release();
		$this->builder->limitBy($limit, $offset);
		return $this;
	}


	public function getIterator()
	{
		if ($this->result === NULL) {
			$this->execute();
		}

		return new EntityIterator($this->result);
	}


	public function getIteratorCount()
	{
		if ($this->resultCount === NULL) {
			if ($this->builder->hasLimitOffsetCluase()) {
				$sql = 'SELECT COUNT(*) FROM (' . $this->builder->getQuerySql() . ') temp';
				$args = $this->builder->getQueryParameters();

			} else {
				$builder = clone $this->builder;
				$builder->select('COUNT(*)');
				$sql = $builder->getQuerySql();
				$args = $builder->getQueryParameters();
			}

			$this->resultCount = $this->connection->queryArgs($sql, $args)->fetchField();
		}

		return $this->resultCount;
	}


	/**
	 * @internal
	 * @return QueryBuilder
	 */
	public function getQueryBuilder()
	{
		return $this->builder;
	}


	public function __clone()
	{
		$this->builder = clone $this->builder;
	}


	protected function release()
	{
		$this->result = NULL;
		$this->resultCount = NULL;
	}


	protected function getParser()
	{
		if (!$this->parser) {
			$this->parser = new QueryBuilderHelper($this->repository->getModel(), $this->repository->getMapper());
		}

		return $this->parser;
	}


	protected function execute()
	{
		$builder = clone $this->builder;
		$builder->select(($this->distinct ? 'DISTINCT ' : '') . $builder->getFromAlias() . '.*');

		$result = $this->connection->queryArgs(
			$builder->getQuerySql(),
			$builder->getQueryParameters()
		);

		$this->result = [];
		while ($data = $result->fetch()) {
			$this->result[] = $this->repository->hydrateEntity($data->toArray());
		}
	}

}
