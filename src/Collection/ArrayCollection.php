<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use Iterator;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\FetchPairsHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\MemberAccessException;
use Nextras\Orm\Repository\IRepository;


class ArrayCollection implements ICollection
{
	/** @var array of callbacks with (\Traversable $entities) arugments */
	public $onEntityFetch = [];

	/** @var array */
	protected $data;

	/** @var IRelationshipMapper|null */
	protected $relationshipMapper;

	/** @var IEntity|null */
	protected $relationshipParent;

	/** @var Iterator|null */
	protected $fetchIterator;

	/** @var IRepository */
	protected $repository;

	/** @var ArrayCollectionHelper */
	protected $helper;

	/** @var callable[] */
	protected $collectionFunctions = [];

	/** @var callable[] */
	protected $collectionFilter = [];

	/** @var array */
	protected $collectionSorter = [];

	/** @var array|null */
	protected $collectionLimit;

	/** @var bool */
	protected $entityFetchEventTriggered = false;


	public function __construct(array $data, IRepository $repository)
	{
		$this->data = $data;
		$this->repository = $repository;
	}


	public function getBy(array $where): ?IEntity
	{
		return $this->findBy($where)->fetch();
	}


	public function getById($id): ?IEntity
	{
		return $this->getBy(['id' => $id]);
	}


	public function findBy(array $where): ICollection
	{
		$collection = clone $this;
		$collection->collectionFilter[] = $this->getHelper()->createFilter($where);
		return $collection;
	}


	public function orderBy($column, string $direction = self::ASC): ICollection
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


	public function resetOrderBy(): ICollection
	{
		$collection = clone $this;
		$collection->collectionSorter = [];
		return $collection;
	}


	public function limitBy(int $limit, int $offset = null): ICollection
	{
		$collection = clone $this;
		$collection->collectionLimit = [$limit, $offset];
		return $collection;
	}


	public function applyFunction(string $functionName, ...$args): ICollection
	{
		$collection = clone $this;
		$collection->collectionFunctions[] = $this->getHelper()->createFunction($functionName, $args);
		return $collection;
	}


	public function fetch(): ?IEntity
	{
		if (!$this->fetchIterator) {
			$this->fetchIterator = $this->getIterator();
		}

		if ($current = $this->fetchIterator->current()) {
			$this->fetchIterator->next();
			return $current;
		}

		return null;
	}


	public function fetchAll()
	{
		return iterator_to_array($this->getIterator());
	}


	public function fetchPairs(string $key = null, string $value = null): array
	{
		return FetchPairsHelper::process($this->getIterator(), $key, $value);
	}


	/** @deprecated */
	public function toCollection($resetOrderBy = false)
	{
		return $resetOrderBy ? $this->resetOrderBy() : clone $this;
	}


	public function __call($name, $args)
	{
		$class = get_class($this);
		throw new MemberAccessException("Call to undefined method $class::$name().");
	}


	public function getIterator()
	{
		if ($this->relationshipParent && $this->relationshipMapper) {
			$collection = clone $this;
			$collection->relationshipMapper = null;
			$collection->relationshipParent = null;
			$entityIterator = $this->relationshipMapper->getIterator($this->relationshipParent, $collection);

		} else {
			$this->processData();
			$entityIterator = new EntityIterator(array_values($this->data));
		}

		if (!$this->entityFetchEventTriggered) {
			foreach ($this->onEntityFetch as $entityFetchCallback) {
				$entityFetchCallback($entityIterator);
			}
			$entityIterator->rewind();
			$this->entityFetchEventTriggered = true;
		}

		return $entityIterator;
	}


	public function count(): int
	{
		return count($this->getIterator());
	}


	public function countStored(): int
	{
		return $this->count();
	}


	public function setRelationshipMapper(IRelationshipMapper $mapper = null, IEntity $parent = null): ICollection
	{
		$this->relationshipMapper = $mapper;
		$this->relationshipParent = $parent;
		return $this;
	}


	public function getRelationshipMapper(): ?IRelationshipMapper
	{
		return $this->relationshipMapper;
	}


	public function setRelationshipParent(IEntity $parent): ICollection
	{
		$collection = clone $this;
		$collection->relationshipParent = $parent;
		return $collection;
	}


	public function subscribeOnEntityFetch(callable $callback): void
	{
		$this->onEntityFetch[] = $callback;
	}


	public function __clone()
	{
		$this->fetchIterator = null;
		$this->entityFetchEventTriggered = false;
	}


	protected function processData()
	{
		if ($this->collectionFunctions || $this->collectionFilter || $this->collectionSorter || $this->collectionLimit) {
			$data = $this->data;

			foreach ($this->collectionFunctions as $function) {
				$data = $function($data);
			}

			foreach ($this->collectionFilter as $filter) {
				$data = array_filter($data, $filter);
			}

			if ($this->collectionSorter) {
				$sorter = $this->getHelper()->createSorter($this->collectionSorter);
				usort($data, $sorter);
			}

			if ($this->collectionLimit) {
				$data = array_slice($data, $this->collectionLimit[1] ?: 0, $this->collectionLimit[0]);
			}

			$this->collectionFunctions = [];
			$this->collectionFilter = [];
			$this->collectionSorter = [];
			$this->collectionLimit = null;
			$this->data = $data;
		}
	}


	protected function getHelper()
	{
		if ($this->helper === null) {
			$this->helper = new ArrayCollectionHelper($this->repository);
		}

		return $this->helper;
	}
}
