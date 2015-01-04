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
use Nextras\Orm\Collection\Helpers\ArrayCollectionClosureHelper;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Collection\Helpers\FindByParserHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\MemberAccessException;


class ArrayCollection implements ICollection
{
	/** @var array */
	protected $data;

	/** @var IRelationshipMapper */
	protected $relationshipMapper;

	/** @var IEntity */
	protected $relationshipParent;

	/** @var Iterator */
	protected $fetchIterator;

	/** @var array */
	protected $collectionFilter = [];

	/** @var array */
	protected $collectionSorter = [];

	/** @var array|NULL */
	protected $collectionLimit;


	public function __construct(array $data, IRelationshipMapper $relationshipMapper = NULL, IEntity $relationshipParent = NULL)
	{
		$this->data = $data;
		$this->relationshipMapper = $relationshipMapper;
		$this->relationshipParent = $relationshipParent;
	}


	public function getBy(array $where)
	{
		return $this->findBy($where)->fetch();
	}


	public function findBy(array $where)
	{
		$collection = clone $this;
		foreach ($where as $column => $value) {
			$collection->collectionFilter[] = ArrayCollectionClosureHelper::createFilter($column, $value);
		}
		return $collection;
	}


	public function orderBy($column, $direction = self::ASC)
	{
		$collection = clone $this;
		if (is_array($column)) {
			foreach ($column as $col => $direction) {
				$collection->collectionSorter[] = [$col, $direction];
			}
		} else {
			$collection->collectionSorter[] = [$column, $direction];
		}
		return $collection;
	}


	public function limitBy($limit, $offset = NULL)
	{
		$collection = clone $this;
		$collection->collectionLimit = [$limit, $offset];
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
			$collection->collectionSorter = [];
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
		if ($parent && $this->relationshipMapper) {
			$collection = clone $this;
			$collection->relationshipMapper = NULL;
			$collection->relationshipParent = NULL;
			return $this->relationshipMapper->getIterator($parent, $collection);

		} else {
			$this->processData();
			return new EntityIterator(array_values($this->data));
		}
	}


	public function count()
	{
		return $this->getEntityCount($this->relationshipParent);
	}


	public function countStored()
	{
		return $this->count();
	}


	public function getEntityCount(IEntity $parent = NULL)
	{
		return count($this->getEntityIterator($parent));
	}


	public function getRelationshipMapper()
	{
		return $this->relationshipMapper;
	}


	public function __clone()
	{
		$this->fetchIterator = NULL;
	}


	protected function processData()
	{
		if ($this->collectionFilter || $this->collectionSorter || $this->collectionLimit) {
			$data = $this->data;
			foreach ($this->collectionFilter as $filter) {
				$data = array_filter($data, $filter);
			}

			if ($this->collectionSorter) {
				$sorter = ArrayCollectionClosureHelper::createSorter($this->collectionSorter);
				usort($data, $sorter);
			}

			if ($this->collectionLimit) {
				$data = array_slice($data, $this->collectionLimit[1], $this->collectionLimit[0]);
			}

			$this->collectionFilter = [];
			$this->collectionSorter = [];
			$this->collectionLimit = NULL;
			$this->data = $data;
		}
	}

}
