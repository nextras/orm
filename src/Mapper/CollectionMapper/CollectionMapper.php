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
use Nextras\Orm\Entity\Collection\EntityIterator;
use Nextras\Orm\Entity\Collection\ICollection;
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
	protected $context;

	/** @var SqlBuilder */
	protected $builder;

	/** @var NetteConditionParser */
	protected $parser;

	/** @var array */
	protected $result;

	/** @var int */
	protected $resultCount;


	public function __construct(IRepository $repository, Context $context, $tableName)
	{
		$this->repository = $repository;
		$this->context = $context;

		$this->builder = new SqlBuilder($tableName, $context->getConnection(), $context->getConventions());
	}


	public function addCondition($column, $value)
	{
		$this->release();
		$condition = $this->getParser()->parse($column);
		$this->builder->addWhere($condition, $value);

		if ($condition !== $column) {
			$this->builder->setGroup($this->getParser()->parse('id'));
		}

		return $this;
	}


	public function orderBy($column, $direction = ICollection::ASC)
	{
		$this->release();
		$this->builder->addOrder($column . ($direction === ICollection::DESC ? ' DESC' : ''));
		return $this;
	}


	public function limitBy($limit, $offset = NULL)
	{
		$this->release();
		$this->builder->setLimit($limit, $offset);
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
			$builder = clone $this->builder;
			$builder->addSelect('COUNT(*)');
			$this->resultCount = $this->context->fetchField(
				$builder->buildSelectQuery(),
				$builder->getParameters()
			);
		}

		return $this->resultCount;
	}


	/**
	 * @internal
	 * @return SqlBuilder
	 */
	public function getSqlBuilder()
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
			$this->parser = new NetteConditionParser($this->repository->getModel(), $this->repository->getMapper());
		}

		return $this->parser;
	}


	protected function execute()
	{
		$result = $this->context->queryArgs($this->builder->buildSelectQuery(), $this->builder->getParameters());
		$this->result = [];
		while ($data = $result->fetch()) {
			$this->result[] = $this->repository->hydrateEntity((array) $data);
		}
	}

}
