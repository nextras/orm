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
				$collection->collectionSorter[] = ArrayCollectionClosureHelper::createSorter($col, $direction);
			}
		} else {
			$collection->collectionSorter[] = ArrayCollectionClosureHelper::createSorter($column, $direction);
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
		$this->processData();
		if ($this->relationshipMapper) {
			$collection = clone $this;
			$collection->relationshipMapper = NULL;
			$collection->relationshipParent = NULL;
			return $this->relationshipMapper->getIterator($this->relationshipParent, $collection);
		}

		return new EntityIterator(array_values($this->data));
	}


	public function count()
	{
		return count($this->getIterator());
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
			foreach ($this->collectionSorter as $sorter) {
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
