<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Nette;

use Nette\Object;
use Nette\Database\Context;
use Nextras\Orm\Entity\Collection\EntityIterator;
use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Mapper\ICollectionMapper;
use Nextras\Orm\Repository\IRepository;


/**
 * CollectionMapper for Nette\Database.
 */
class CollectionMapper extends Object implements ICollectionMapper
{
	/** @var IRepository */
	protected $repository;

	/** @var Context */
	protected $context;

	/** @var SqlBuilder */
	protected $builder;

	/** @var ConditionParser */
	protected $parser;

	/** @var array */
	protected $result;

	/** @var int */
	protected $resultCount;


	public function __construct(IRepository $repository, Context $context, $tableName)
	{
		$this->repository = $repository;
		$this->context = $context;

		$this->builder = new SqlBuilder($tableName, $context);
	}


	public function addCondition($column, $value)
	{
		$this->release();
		$condition = $this->getParser()->parse($column, $value);
		$this->builder->addWhere($condition, $value);

		if ($condition !== $column) {
			foreach ($this->repository->getMapper()->getStorageReflection()->getStoragePrimaryKey() as $primaryKey) {
				$this->builder->setGroup($this->builder->getTableName() . '.' . $primaryKey);
			}
		}

		return $this;
	}


	public function orderBy($column, $direction = ICollection::ASC)
	{
		$this->release();
		$column = $this->getParser()->parse($column, NULL);
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
			$this->resultCount = $this->context->queryArgs($builder->buildSelectQuery(), $builder->getParameters())->fetchField();
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
			$this->parser = new ConditionParser($this->repository->getModel(), $this->repository->getMapper());
		}

		return $this->parser;
	}


	protected function execute()
	{
		$result = $this->context->queryArgs($this->builder->buildSelectQuery(['*']), $this->builder->getParameters());
		$this->result = [];
		while ($data = $result->fetch()) {
			$this->result[] = $this->repository->hydrateEntity((array) $data);
		}
	}

}
