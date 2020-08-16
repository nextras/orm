<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use EmptyIterator;
use Iterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Mapper\IRelationshipMapper;


class EmptyCollection implements ICollection
{
	/** @var IRelationshipMapper|null */
	private $relationshipMapper;


	public function getBy(array $conds): ?IEntity
	{
		return null;
	}


	public function getByChecked(array $conds): IEntity
	{
		throw new NoResultException();
	}


	public function getById($id): ?IEntity
	{
		return null;
	}


	public function getByIdChecked($id): IEntity
	{
		throw new NoResultException();
	}


	public function findBy(array $conds): ICollection
	{
		return clone $this;
	}


	public function orderBy($expression, string $direction = self::ASC): ICollection
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


	public function fetch(): ?IEntity
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


	public function getIterator(): Iterator
	{
		return new EmptyIterator();
	}


	public function setRelationshipMapper(IRelationshipMapper $mapper = null, IEntity $parent = null): ICollection
	{
		$this->relationshipMapper = $mapper;
		return $this;
	}


	public function getRelationshipMapper(): ?IRelationshipMapper
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


	public function subscribeOnEntityFetch(callable $callback): void
	{
	}
}
