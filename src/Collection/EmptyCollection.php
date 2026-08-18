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


	#[\NoDiscard]
	public function getBy(array $conds): ?IEntity
	{
		return null;
	}


	#[\NoDiscard]
	public function getByChecked(array $conds): IEntity
	{
		throw new NoResultException();
	}


	#[\NoDiscard]
	public function getById($id): ?IEntity
	{
		return null;
	}


	#[\NoDiscard]
	public function getByIdChecked($id): IEntity
	{
		throw new NoResultException();
	}


	#[\NoDiscard('Method returns a new collection instance, the original collection is not modified.')]
	public function findBy(array $conds): ICollection
	{
		return clone $this;
	}


	#[\NoDiscard('Method returns a new collection instance, the original collection is not modified.')]
	public function orderBy($expression, string $direction = self::ASC): ICollection
	{
		return clone $this;
	}


	#[\NoDiscard('Method returns a new collection instance, the original collection is not modified.')]
	public function resetOrderBy(): ICollection
	{
		return clone $this;
	}


	#[\NoDiscard('Method returns a new collection instance, the original collection is not modified.')]
	public function limitBy(int $limit, int|null $offset = null): ICollection
	{
		return clone $this;
	}


	#[\NoDiscard]
	public function fetch(): ?IEntity
	{
		return null;
	}


	#[\NoDiscard]
	public function fetchChecked(): IEntity
	{
		throw new NoResultException();
	}


	#[\NoDiscard]
	public function fetchAll(): array
	{
		return [];
	}


	#[\NoDiscard]
	public function fetchPairs(string|null $key = null, string|null $value = null): array
	{
		return [];
	}


	#[\NoDiscard]
	public function getIterator(): Iterator
	{
		return new EmptyIterator();
	}


	public function setRelationshipMapper(IRelationshipMapper|null $mapper): ICollection
	{
		$this->relationshipMapper = $mapper;
		return $this;
	}


	#[\NoDiscard]
	public function getRelationshipMapper(): ?IRelationshipMapper
	{
		return $this->relationshipMapper;
	}


	#[\NoDiscard('Method returns a new collection instance, the original collection is not modified.')]
	public function setRelationshipParent(IEntity $parent): ICollection
	{
		return clone $this;
	}


	#[\NoDiscard]
	public function countStored(): int
	{
		return 0;
	}


	#[\NoDiscard]
	public function count(): int
	{
		return 0;
	}


	#[\NoDiscard]
	public function toMemoryCollection(): MemoryCollection
	{
		return clone $this;
	}


	public function subscribeOnEntityFetch(callable $callback): void
	{
	}
}
