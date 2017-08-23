<?php declare(strict_types = 1);

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;


class HasManyCollection implements ICollection
{

	/** @var ICollection */
	private $innerCollection;

	/** @var array */
	private $toAdd;

	/** @var array */
	private $toRemove;

	/** @var array */
	private $conditions = [];

	/** @var array */
	private $sorting = [];

	/** @var int|null */
	private $limit;

	/** @var int|null */
	private $offset;

	/** @var IEntity */
	private $relationshipParent;

	/** @var callable[] */
	private $onEntityFetch = [];

	/** @var ArrayCollection|NULL */
	private $resultCollection;

	/** @var IRepository */
	private $repository;


	public function __construct(ICollection $innerCollection, IRepository $repository, array $toAdd, array $toRemove)
	{
		$this->innerCollection = $innerCollection;
		$this->toAdd = $toAdd;
		$this->toRemove = $toRemove;
		$this->repository = $repository;
	}


	public function getIterator()
	{
		$this->createResultCollection();
		return $this->resultCollection->getIterator();
	}


	private function createResultCollection()
	{
		if ($this->resultCollection !== null) {
			return;
		}
		$all = [];
		foreach ($this->innerCollection as $entity) {
			$all[spl_object_hash($entity)] = $entity;
		}
		foreach ($this->toAdd as $hash => $entity) {
			$all[$hash] = $entity;
		}
		foreach ($this->toRemove as $hash => $entity) {
			unset($all[$hash]);
		}

		$this->resultCollection = $this->createCollection(array_values($all));
	}


	public function getBy(array $where)
	{
		return $this->findBy($where)->limitBy(1)->fetch();
	}


	public function findBy(array $where): ICollection
	{
		$self = clone $this;
		$self->conditions[] = $where;
		$self->innerCollection = $this->innerCollection->findBy($where);
		return $self;
	}


	public function orderBy($column, string $direction = self::ASC): ICollection
	{
		$self = clone $this;
		$self->sorting[] = [$column, $direction];
		$self->innerCollection = $this->innerCollection->orderBy($column, $direction);
		return $self;
	}


	public function resetOrderBy(): ICollection
	{
		$self = clone $this;
		$self->sorting = [];
		$self->innerCollection = $this->innerCollection->resetOrderBy();
		return $self;
	}


	public function limitBy(int $limit, int $offset = null): ICollection
	{
		$self = clone $this;
		$self->limit = $limit;
		$self->offset = $offset;
		$self->innerCollection = $this->innerCollection->limitBy($limit, $offset);
		return $self;
	}


	public function fetch()
	{
		$this->createResultCollection();
		return $this->resultCollection->fetch();
	}


	public function fetchAll()
	{
		return iterator_to_array($this->getIterator());
	}


	public function fetchPairs(string $key = null, string $value = null): array
	{
		return FetchPairsHelper::process($this->getIterator(), $key, $value);
	}


	public function setRelationshipMapper(IRelationshipMapper $mapper = null): ICollection
	{
		$this->innerCollection->setRelationshipMapper($mapper);

		return $this;
	}


	public function getRelationshipMapper()
	{
		return $this->innerCollection->getRelationshipMapper();
	}


	public function setRelationshipParent(IEntity $parent): ICollection
	{
		$self = clone $this;
		$self->innerCollection = $this->innerCollection->setRelationshipParent($parent);
		$self->relationshipParent = $parent;
		return $self;
	}


	public function countStored(): int
	{
		$count = $this->innerCollection->countStored();
		$count -= $this->createCollection($this->toRemove)->countStored();
		$count += $this->createCollection($this->toAdd)->countStored();
		return $count;
	}


	public function subscribeOnEntityFetch(callable $callback)
	{
		$this->onEntityFetch[] = $callback;
		$this->innerCollection->subscribeOnEntityFetch($callback);
	}


	public function count()
	{
		return iterator_count($this->getIterator());
	}


	private function createCollection(array $data): ICollection
	{
		$collection = new ArrayCollection($data, $this->repository);
		foreach ($this->conditions as $condition) {
			$collection = $collection->findBy($condition);
		}
		foreach ($this->sorting as $sorting) {
			$collection = $collection->orderBy(...$sorting);
		}
		if ($this->limit !== null) {
			$collection = $collection->limitBy($this->limit, $this->offset);
		}
		$collection = $collection->setRelationshipMapper($this->innerCollection->getRelationshipMapper());
		if ($this->relationshipParent) {
			$collection = $collection->setRelationshipParent($this->relationshipParent);
		}
		foreach ($this->onEntityFetch as $callback) {
			$collection->subscribeOnEntityFetch($callback);
		}

		return $collection;
	}

}
