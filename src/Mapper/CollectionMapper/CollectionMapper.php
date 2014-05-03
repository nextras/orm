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
use Nextras\Orm\Entity\Collection\Collection;
use Nextras\Orm\Entity\Collection\EntityIterator;
use Nextras\Orm\Mapper\NetteConditionParser;
use Nextras\Orm\Repository\IRepository;


/**
 * CollectionMapper for Nette Framework.
 */
class CollectionMapper extends Object implements ICollectionMapper
{
	/** @var IRepository */
	protected $repository;

	/** @var Context */
	protected $databaseContext;

	/** @var string */
	protected $tableName;

	/** @var SqlBuilder */
	protected $builder;

	/** @var NetteConditionParser */
	protected $parser;

	/** @var array */
	protected $result;

	/** @var int */
	protected $resultCount;


	public function __construct(IRepository $repository, Context $databaseContext, $tableName)
	{
		$this->repository = $repository;
		$this->databaseContext = $databaseContext;
		$this->tableName = $tableName;

		$this->builder = new SqlBuilder($tableName, $databaseContext->getConnection(), $databaseContext->getConventions());
		$this->parser = new NetteConditionParser($repository->getModel(), $repository->getModel()->getMetadataStorage());
	}


	protected function getParserMapper()
	{
		return $this->repository->getMapper();
	}


	/**
	 * @internal
	 * @return SqlBuilder
	 */
	public function getSqlBuilder()
	{
		return $this->builder;
	}


	public function addWhere($conditions)
	{
		$this->release();
		foreach ($conditions as $key => $val) {
			$key = $this->parser->parse($key, $this->getParserMapper());
			$this->builder->addWhere($key, $val);
		}
		return $this;
	}


	public function addGroupBy($group)
	{
		$groupBy = $this->builder->getGroup();
		$this->builder->setGroup($groupBy ? $groupBy . ', ' . $group : $group);
	}


	public function addHaving($having)
	{
		// todo: add vs set having
		$this->builder->setHaving($having);
	}


	public function addOrder($column, $direction)
	{
		$this->release();
		$this->builder->addOrder($column . ($direction === Collection::DESC ? ' DESC' : ''));
		return $this;
	}


	public function setLimit($limit, $offset)
	{
		$this->release();
		$this->builder->setLimit($limit, $offset);
		return $this;
	}


	public function release()
	{
		$this->result = NULL;
		$this->resultCount = NULL;
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
			$builder = clone $this->builder;
			$builder->addSelect('COUNT(*)');
			$this->resultCount = $this->databaseContext->fetchField(
				$builder->buildSelectQuery(),
				$builder->getParameters()
			);
		}

		return $this->resultCount;
	}


	protected function execute()
	{
		$result = $this->databaseContext->queryArgs($this->builder->buildSelectQuery(), $this->builder->getParameters());
		$this->result = [];
		while ($data = $result->fetch()) {
			$this->result[] = $this->repository->hydrateEntity((array) $data);
		}
	}

}
