<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IRepository;


interface IModel
{
	/**
	 * Returns true if repository with name is attached to model.
	 */
	#[\NoDiscard]
	public function hasRepositoryByName(string $name): bool;

	/**
	 * Returns repository by repository name.
	 * @return IRepository<*>
	 */
	#[\NoDiscard]
	public function getRepositoryByName(string $name): IRepository;

	/**
	 * Returns true if repository class is attached to model.
	 * @template T of IRepository
	 * @param class-string<T> $className
	 */
	#[\NoDiscard]
	public function hasRepository(string $className): bool;

	/**
	 * Returns repository by repository class.
	 * @template T of IRepository<*>
	 * @param class-string<T> $className
	 * @return T
	 */
	#[\NoDiscard]
	public function getRepository(string $className): IRepository;

	/**
	 * Returns repository associated for entity type.
	 * @template E of IEntity
	 * @param E|class-string<E> $entity
	 * @return IRepository<E>
	 */
	#[\NoDiscard]
	public function getRepositoryForEntity($entity): IRepository;

	/**
	 * Returns entity metadata storage.
	 */
	#[\NoDiscard]
	public function getMetadataStorage(): MetadataStorage;

	/**
	 * Persist the entity with cascade.
	 * @template E of IEntity
	 * @param E $entity
	 * @return E
	 */
	public function persist(IEntity $entity, bool $withCascade = true): IEntity;

	/**
	 * Persist the entity with cascade and flushes the model.
	 * @template E of IEntity
	 * @param E $entity
	 * @return E
	 */
	public function persistAndFlush(IEntity $entity): IEntity;

	/**
	 * Removes the entity with cascade.
	 * @template E of IEntity
	 * @param E $entity
	 * @return E
	 */
	public function remove(IEntity $entity, bool $withCascade = true): IEntity;

	/**
	 * Removes the entity with cascade and flushes the model.
	 * @template E of IEntity
	 * @param E $entity
	 * @return E
	 */
	public function removeAndFlush(IEntity $entity, bool $withCascade = true): IEntity;

	/**
	 * Flushes all persisted changes in repositories.
	 */
	public function flush(): void;

	/**
	 * Rolls back unflushed mapper writes and invalidates loaded and affected entities and pending flush notifications.
	 * Must not be called inside transactional(); throw an exception to abort that scope.
	 */
	public function rollback(): void;

	/**
	 * Runs a callback once in an outermost transaction and flushes before committing.
	 * All repositories must support transactions and share one coordinator.
	 * Nested ORM scopes and existing connection transactions are rejected.
	 * On failure loaded and affected entities are invalidated. On cleanup failure discard the model.
	 * Load entities inside the callback when their reads must belong to the transaction.
	 * @template T
	 * @param callable(static): T $callback
	 * @return T
	 */
	public function transactional(callable $callback): mixed;

	/**
	 * Clears repository identity map and other possible caches.
	 * Make sure that all references to already used entites are released,
	 * this makes possible to free the memory for garbage collector.
	 * Orm will not allow you to work with these entities anymore.
	 */
	public function clear(): void;

	/**
	 * Refreshes all entities' data.
	 */
	public function refreshAll(bool $allowOverwrite = false): void;
}
