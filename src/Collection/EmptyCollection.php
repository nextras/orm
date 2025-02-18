<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use EmptyIterator;
use Iterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\NoResultException;
use Nextras\Orm\Mapper\IRelationshipMapper;


/**
 * @template E of IEntity
 * @implements ICollection<E>
 * @implements MemoryCollection<E>
 */
final class EmptyCollection implements ICollection, MemoryCollection
{
	private ?IRelationshipMapper $relationshipMapper = null;


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


	public function limitBy(int $limit, int|null $offset = null): ICollection
	{
		return clone $this;
	}


	public function fetch(): ?IEntity
	{
		return null;
	}


	public function fetchChecked(): IEntity
	{
		throw new NoResultException();
	}


	public function fetchAll(): array
	{
		return [];
	}


	public function fetchPairs(string|null $key = null, string|null $value = null): array
	{
		return [];
	}


	public function getIterator(): Iterator
	{
		return new EmptyIterator();
	}


	public function setRelationshipMapper(IRelationshipMapper|null $mapper): ICollection
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


	public function toMemoryCollection(): MemoryCollection
	{
		return clone $this;
	}


	public function subscribeOnEntityFetch(callable $callback): void
	{
	}
}
