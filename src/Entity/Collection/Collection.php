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
use Iterator;
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

	/** @var Iterator */
	protected $fetchIterator;


	public function __construct(ICollectionMapper $collectionMapper, Closure $iteratorFactory = NULL, Closure $iteratorCountFactory = NULL)
	{
		$this->collectionMapper = $collectionMapper;
		$this->iteratorFactory = $iteratorFactory;
		$this->iteratorCountFactory = $iteratorCountFactory;
	}


	public function getBy(array $where)
	{
		return $this->findBy($where)->limitBy(1)->fetch();
	}


	public function findBy(array $where)
	{
		$collection = clone $this;
		foreach ($where as $column => $value) {
			$collection->collectionMapper->addCondition($column, $value);
		}
		return $collection;
	}


	public function orderBy($column, $direction = ICollection::ASC)
	{
		$collection = clone $this;
		if (is_array($column)) {
			foreach ($column as $col => $direction) {
				$collection->collectionMapper->orderBy($col, $direction);
			}
		} else {
			$collection->collectionMapper->orderBy($column, $direction);
		}
		return $collection;
	}


	public function limitBy($limit, $offset = NULL)
	{
		$this->collectionMapper->limitBy($limit, $offset);
		return $this;
	}


	public function fetch()
	{
		if (!$this->fetchIterator) {
			$this->fetchIterator = $this->getIterator();
		}

		while ($current = $this->fetchIterator->current()) {
			$this->fetchIterator->next();
			return $current;
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


	public function __call($name, $args)
	{
		if (FindByParser::parse($name, $args)) {
			return call_user_func([$this, $name], $args);

		} else {
			$class = get_class($this);
			throw new MemberAccessException("Call to undefined method $class::$name().");
		}
	}


	public function getIterator()
	{
		if (!$this->iteratorFactory) {
			return $this->collectionMapper->getIterator();
		}

		$cb = $this->iteratorFactory;
		return $cb($this);
	}


	public function count()
	{
		if (!$this->iteratorCountFactory) {
			return $this->collectionMapper->getIteratorCount();
		}

		$cb = $this->iteratorCountFactory;
		return $cb($this);
	}


	public function getMapper()
	{
		return $this->collectionMapper;
	}


	public function __clone()
	{
		$this->collectionMapper = clone $this->collectionMapper;
	}

}
