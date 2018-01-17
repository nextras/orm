<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use EmptyIterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapper;


final class EmptyCollection implements ICollection
{
	/** @var IRelationshipMapper|null */
	private $relationshipMapper;


	/**
	 * @return null
	 */
	public function getBy(array $where)
	{
		return null;
	}


	/**
	 * @return null
	 */
	public function getById($id)
	{
		return null;
	}


	public function findBy(array $where): ICollection
	{
		return clone $this;
	}


	public function orderBy($column, string $direction = self::ASC): ICollection
	{
		return clone $this;
	}


	public function resetOrderBy(): ICollection
	{
		return clone $this;
	}


	public function limitBy(int $limit, int $offset = null): ICollection
	{
		return clone $this;
	}


	public function applyFunction(string $functionName, ...$args): ICollection
	{
		return clone $this;
	}


	/**
	 * @return null
	 */
	public function fetch()
	{
		return null;
	}


	/**
	 * @return       array
	 */
	public function fetchAll()
	{
		return [];
	}


	public function fetchPairs(string $key = null, string $value = null): array
	{
		return [];
	}


	/**
	 * @deprecated 
	 *
	 * @return     EmptyCollection
	 */
	public function toCollection($resetOrderBy = false)
	{
		return clone $this;
	}


	/**
	 * @return EmptyIterator
	 */
	public function getIterator()
	{
		return new EmptyIterator();
	}


	public function setRelationshipMapper(IRelationshipMapper $mapper = null, IEntity $parent = null): ICollection
	{
		$this->relationshipMapper = $mapper;
		return $this;
	}


	/**
	 * @return IRelationshipMapper|null
	 */
	public function getRelationshipMapper()
	{
		return $this->relationshipMapper;
	}


	public function setRelationshipParent(IEntity $parent): ICollection
	{
		return clone $this;
	}


	public function countStored(): int
	{
		return 0;
	}


	public function count(): int
	{
		return 0;
	}


	/**
	 * @return void
	 */
	public function subscribeOnEntityFetch(callable $callback)
	{
	}
}
