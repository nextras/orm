<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Collection;

use Closure;
use Nextras\Orm\Mapper\CollectionMapper\ICollectionMapper;
use Nextras\Orm\MemberAccessException;
use Nextras\Orm\NotImplementedException;


class Collection implements ICollection
{
	/** @var ICollectionMapper */
	protected $collectionMapper;

	/** @var Closure */
	protected $iteratorFactory;

	/** @var Closure */
	protected $iteratorCountFactory;


	public function __construct(ICollectionMapper $collectionMapper, Closure $iteratorFactory = NULL, Closure $iteratorCountFactory = NULL)
	{
		$this->collectionMapper = $collectionMapper;
		$this->iteratorFactory = $iteratorFactory;
		$this->iteratorCountFactory = $iteratorCountFactory;
	}


	public function orderBy($column, $direction = self::ASC)
	{
		if (is_array($column)) {
			foreach ($column as $col => $d) {
				$this->orderBy($col, $d);
			}
		} else {
			$this->collectionMapper->addOrder($column, $direction);
		}
		$this->release();
		return $this;
	}


	public function limit($limit, $offset = NULL)
	{
		$this->collectionMapper->setLimit($limit, $offset);
		$this->release();
		return $this;
	}


	public function fetch()
	{
		// todo: check
		foreach ($this->getIterator() as $row) {
			return $row;
		}

		return FALSE;
	}


	public function fetchAll()
	{
		return iterator_to_array($this->getIterator());
	}


	public function fetchAssoc($assoc)
	{
		throw new NotImplementedException;
	}


	public function fetchPairs($key = NULL, $value = NULL)
	{
		return FetchPairsHelper::process($this->getIterator(), $key, $value);
	}


	public function toCollection()
	{
		return clone $this;
	}


	public function getBy(array $where)
	{
		return $this->findBy($where)->limit(1)->fetch();
	}


	public function findBy(array $where)
	{
		$collection = clone $this;
		$collection->collectionMapper->addWhere($where);
		$collection->release();
		return $collection;
	}


	public function __call($name, $args)
	{
		if (FindByParser::parse($name, $args)) {
			return call_user_func([$this, $name], $args);

		} else {
			$class = get_class($this);
			throw new MemberAccessException("Call to undefined method $class::$name().");
		}
	}


	public function release()
	{
		$this->collectionMapper->release();
	}


	public function getIterator()
	{
		if (!$this->iteratorFactory) {
			return $this->collectionMapper->getIterator();
		}

		$cb = $this->iteratorFactory;
		return $cb($this->collectionMapper);
	}


	public function count()
	{
		if (!$this->iteratorCountFactory) {
			return $this->collectionMapper->getIteratorCount();
		}

		$cb = $this->iteratorCountFactory;
		return $cb($this->collectionMapper);
	}


	public function __clone()
	{
		$this->collectionMapper = clone $this->collectionMapper;
	}

}
