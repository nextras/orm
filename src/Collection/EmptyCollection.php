<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use EmptyIterator;
use Iterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapper;


final class EmptyCollection implements ICollection
{
	/** @var IRelationshipMapper */
	private $relationshipMapper;


	public function getBy(array $where)
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


	public function fetch()
	{
		return null;
	}


	public function fetchAll()
	{
		return [];
	}


	public function fetchPairs(string $key = null, string $value = null): array
	{
		return [];
	}


	/** @deprecated */
	public function toCollection($resetOrderBy = false)
	{
		return clone $this;
	}


	public function getIterator()
	{
		return new EmptyIterator();
	}


	public function getEntityIterator(IEntity $parent = null): Iterator
	{
		return new EmptyIterator();
	}


	public function getEntityCount(IEntity $parent = null): int
	{
		return 0;
	}


	public function setRelationshipMapping(IRelationshipMapper $mapper = null, IEntity $parent = null): ICollection
	{
		$this->relationshipMapper = $mapper;
		return $this;
	}


	public function getRelationshipMapper(): IRelationshipMapper
	{
		return $this->relationshipMapper;
	}


	public function countStored(): int
	{
		return 0;
	}


	public function count(): int
	{
		return 0;
	}
}
