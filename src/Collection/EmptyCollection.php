<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapper;


final class EmptyCollection implements ICollection
{
	/** @var IRelationshipMapper */
	private $relationshipMapper;


	public function getBy(array $where)
	{
		return NULL;
	}


	public function findBy(array $where)
	{
		return clone $this;
	}


	public function orderBy($column, $direction = self::ASC)
	{
		return clone $this;
	}


	public function resetOrderBy()
	{
		return clone $this;
	}


	public function limitBy($limit, $offset = NULL)
	{
		return clone $this;
	}


	public function fetch()
	{
		return NULL;
	}


	public function fetchAll()
	{
		return [];
	}


	public function fetchPairs($key = NULL, $value = NULL)
	{
		return [];
	}


	/** @deprecated */
	public function toCollection($resetOrderBy = FALSE)
	{
		return clone $this;
	}


	public function getIterator()
	{
		return new \EmptyIterator();
	}


	public function getEntityIterator(IEntity $parent = NULL)
	{
		return new \EmptyIterator();
	}


	public function getEntityCount(IEntity $parent = NULL)
	{
		return 0;
	}


	public function setRelationshipMapping(IRelationshipMapper $mapper = NULL, IEntity $parent = NULL)
	{
		$this->relationshipMapper = $mapper;
		return $this;
	}


	public function getRelationshipMapper()
	{
		return $this->relationshipMapper;
	}


	public function countStored()
	{
		return 0;
	}


	public function count()
	{
		return 0;
	}

}
