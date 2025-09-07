<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;


use DateTimeImmutable;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Exception\InvalidArgumentException;
use ReflectionClass;


/**
 * @template E of IEntity
 */
class IdentityMap
{
	/** @var array<int|string, \WeakReference<E>|false> */
	private array $entities = [];

	/** @var array<int|string, bool> */
	private array $entitiesForRefresh = [];

	/** @var array<class-string<E>, ReflectionClass<E>> */
	private array $entityReflections = [];


	/**
	 * @param IRepository<E> $repository
	 */
	public function __construct(
		private readonly IRepository $repository,
	)
	{
	}


	/**
	 * @param array|int|mixed $id
	 */
	public function hasById($id): bool
	{
		$idHash = $this->getIdHash($id);
		return isset($this->entities[$idHash]) && ($this->entities[$idHash] === false || $this->entities[$idHash]->get() !== null);
	}


	/**
	 * @param array|int|mixed $id
	 * @return E|null|false
	 */
	public function getById($id)
	{
		$idHash = $this->getIdHash($id);
		if (!isset($this->entities[$idHash]) || $this->entities[$idHash] === false || isset($this->entitiesForRefresh[$idHash])) {
			return null;
		}

		$entity = $this->entities[$idHash]->get();
		if ($entity instanceof IEntityHasPreloadContainer) {
			$entity->setPreloadContainer(null);
		}
		return $entity;
	}


	/**
	 * @param E $entity
	 */
	public function add(IEntity $entity): void
	{
		$id = $this->getIdHash($entity->getPersistedId());
		$this->entities[$id] = \WeakReference::create($entity);
	}


	/**
	 * @param array|int|mixed $id
	 */
	public function remove($id): void
	{
		$idHash = $this->getIdHash($id);
		$this->entities[$idHash] = false;
		unset($this->entitiesForRefresh[$idHash]);
	}


	/**
	 * @param array<string, mixed> $data
	 * @return E|null
	 */
	public function create(array $data): ?IEntity
	{
		$entity = $this->createEntity($data);
		$id = $this->getIdHash($entity->getPersistedId());

		if (isset($this->entities[$id])) {
			if ($this->entities[$id] === false) {
				$this->repository->detach($entity);
				return null;
			}
			$existingEntity = $this->entities[$id]->get();
			if ($existingEntity === null) {
				// The entity was garbage collected, use the new one
				$this->entities[$id] = \WeakReference::create($entity);
				return $entity;
			}
			$this->repository->detach($entity);
			if (isset($this->entitiesForRefresh[$id])) {
				// entity can be detached because of delete try
				$this->repository->attach($existingEntity);
				$existingEntity->onRefresh($data);
				unset($this->entitiesForRefresh[$id]);
			}
			return $existingEntity;
		}

		$this->entities[$id] = \WeakReference::create($entity);
		return $entity;
	}


	/**
	 * @return list<E>
	 */
	public function getAll(): array
	{
		$all = [];
		foreach ($this->entities as $entity) {
			if ($entity !== false && $entity->get() !== null) {
				$all[] = $entity->get();
			}
		}
		return $all;
	}


	public function check(IEntity $entity): void
	{
		if (!in_array(get_class($entity), ($this->repository)::getEntityClassNames(), true)) {
			throw new InvalidArgumentException("Entity '" . get_class($entity) . "' is not accepted by '" . get_class($this->repository) . "' repository.");
		}
	}


	public function destroyAllEntities(): void
	{
		foreach ($this->entities as $entity) {
			if ($entity !== false && $entity->get() !== null) {
				$this->repository->detach($entity->get());
				$entity->get()->onFree();
			}
		}

		$this->entities = [];
	}


	public function markForRefresh(IEntity $entity): void
	{
		$id = $this->getIdHash($entity->getPersistedId());
		$this->entitiesForRefresh[$id] = true;
	}


	public function isMarkedForRefresh(IEntity $entity): bool
	{
		$id = $this->getIdHash($entity->getPersistedId());
		return isset($this->entitiesForRefresh[$id]);
	}


	/**
	 * @param array<string, mixed> $data
	 * @return E
	 */
	protected function createEntity(array $data): IEntity
	{
		$entityClass = $this->repository->getEntityClassName($data);
		$this->entityReflections[$entityClass] ??= new ReflectionClass($entityClass);
		$entity = $this->entityReflections[$entityClass]->newInstanceWithoutConstructor();
		$this->repository->attach($entity);
		$entity->onLoad($data);
		return $entity;
	}


	/**
	 * @param mixed $id
	 */
	protected function getIdHash($id): string
	{
		if (!is_array($id)) {
			return $id instanceof DateTimeImmutable
				? $id->format('c.u')
				: (string) $id;
		}

		return implode(
			',',
			array_map(
				function ($id): string {
					return $id instanceof DateTimeImmutable
						? $id->format('c.u')
						: (string) $id;
				},
				$id,
			),
		);
	}
}
