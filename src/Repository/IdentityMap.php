<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\InvalidArgumentException;
use ReflectionClass;


class IdentityMap
{
	/** @var IRepository */
	private $repository;

	/** @var array of IEntity|bool */
	private $entities = [];

	/** @var array */
	private $entitiesForRefresh = [];

	/** @var ReflectionClass[] */
	private $entityReflections;


	public function __construct(IRepository $repository)
	{
		$this->repository = $repository;
	}


	/**
	 * @param  array|int|mixed $id
	 */
	public function hasById($id): bool
	{
		return isset($this->entities[implode(',', (array) $id)]);
	}


	/**
	 * @param  array|int|mixed $id
	 * @return IEntity|null|false
	 */
	public function getById($id)
	{
		$id = implode(',', (array) $id);
		if (!isset($this->entities[$id])) {
			return null;
		}

		return $this->entities[$id];
	}


	public function add(IEntity $entity)
	{
		$this->entities[implode(',', (array) $entity->getPersistedId())] = $entity;
	}


	/**
	 * @param  array|int|mixed $id
	 */
	public function remove($id)
	{
		$id = implode(',', (array) $id);
		$this->entities[$id] = false;
		unset($this->entitiesForRefresh[$id]);
	}


	/**
	 * @return IEntity|null
	 */
	public function create(array $data)
	{
		$entity = $this->createEntity($data);
		$id = implode(',', (array) $entity->getPersistedId());

		if (isset($this->entities[$id])) {
			$this->repository->detach($entity);
			if (!$this->entities[$id]) {
				return null;
			}
			$entity = $this->entities[$id];
			if (isset($this->entitiesForRefresh[$id])) {
				$entity->onRefresh($data);
				unset($this->entitiesForRefresh[$id]);
			}
			return $entity;

		}

		return $this->entities[$id] = $entity; // = intentionally
	}


	/**
	 * @return IEntity[]
	 */
	public function getAll()
	{
		return array_filter($this->entities);
	}


	public function check(IEntity $entity)
	{
		if (!in_array(get_class($entity), $this->repository->getEntityClassNames(), true)) {
			throw new InvalidArgumentException("Entity '" . get_class($entity) . "' is not accepted by '" . get_class($this->repository) . "' repository.");
		}
	}


	public function destroyAllEntities()
	{
		foreach ($this->entities as $entity) {
			if ($entity) {
				$this->repository->detach($entity);
				$entity->onFree();
			}
		}

		$this->entities = [];
	}


	public function markForRefresh(IEntity $entity)
	{
		$id = implode(',', (array) $entity->getPersistedId());
		$this->entitiesForRefresh[$id] = true;
	}


	public function isMarkedForRefresh(IEntity $entity): bool
	{
		$id = implode(',', (array) $entity->getPersistedId());
		return isset($this->entitiesForRefresh[$id]);
	}


	protected function createEntity(array $data): IEntity
	{
		$entityClass = $this->repository->getEntityClassName($data);

		if (!isset($this->entityReflections[$entityClass])) {
			$this->entityReflections[$entityClass] = new ReflectionClass($entityClass);
		}

		$entity = $this->entityReflections[$entityClass]->newInstanceWithoutConstructor();
		$this->repository->attach($entity);
		$entity->onLoad($data);
		return $entity;
	}
}
