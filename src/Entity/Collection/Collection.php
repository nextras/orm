<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Collection;

use Iterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\ICollectionMapper;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\MemberAccessException;


class Collection implements ICollection
{
	/** @var ICollectionMapper */
	protected $collectionMapper;

	/** @var IRelationshipMapper */
	protected $relationshipMapper;

	/** @var IEntity */
	protected $relationshipParent;

	/** @var Iterator */
	protected $fetchIterator;


	public function __construct(ICollectionMapper $collectionMapper, IRelationshipMapper $relationshipMapper = NULL, IEntity $relationshipParent = NULL)
	{
		$this->collectionMapper = $collectionMapper;
		$this->relationshipMapper = $relationshipMapper;
		$this->relationshipParent = $relationshipParent;
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

		if ($current = $this->fetchIterator->current()) {
			$this->fetchIterator->next();
			return $current;
		}

		return FALSE;
	}


	public function fetchAll()
	{
		return iterator_to_array($this->getIterator());
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
		if ($this->relationshipMapper) {
			return $this->relationshipMapper->getIterator($this->relationshipParent, $this);
		}

		return $this->collectionMapper->getIterator();
	}


	public function count()
	{
		if ($this->relationshipMapper) {
			return $this->relationshipMapper->getIteratorCount($this->relationshipParent, $this);
		}

		return $this->collectionMapper->getIteratorCount();
	}


	public function getRelationshipMapper()
	{
		return $this->relationshipMapper;
	}


	public function getCollectionMapper()
	{
		return $this->collectionMapper;
	}


	public function __clone()
	{
		$this->collectionMapper = clone $this->collectionMapper;
	}

}
