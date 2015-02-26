<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Collection;

use Iterator;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Collection\Helpers\FindByParserHelper;
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


	public function __construct(ICollectionMapper $collectionMapper)
	{
		$this->collectionMapper = $collectionMapper;
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
				$collection->collectionMapper->addOrderBy($col, $direction);
			}
		} else {
			$collection->collectionMapper->addOrderBy($column, $direction);
		}
		return $collection;
	}


	public function limitBy($limit, $offset = NULL)
	{
		$collection = clone $this;
		$collection->collectionMapper->limitBy($limit, $offset);
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


	public function toCollection($resetOrderBy = FALSE)
	{
		$collection = clone $this;
		if ($resetOrderBy) {
			$collection->collectionMapper->resetOrderBy();
		}
		return $collection;
	}


	public function __call($name, $args)
	{
		if (FindByParserHelper::parse($name, $args)) {
			return call_user_func([$this, $name], $args);

		} else {
			$class = get_class($this);
			throw new MemberAccessException("Call to undefined method $class::$name().");
		}
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

		return $this->collectionMapper->getIterator();
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

		return $this->collectionMapper->getIteratorCount();
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


	public function getCollectionMapper()
	{
		return $this->collectionMapper;
	}


	public function __clone()
	{
		$this->collectionMapper = clone $this->collectionMapper;
		$this->fetchIterator = NULL;
	}

}
