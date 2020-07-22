<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IRepository;


interface IModel
{
	/**
	 * Returns true if repository with name is attached to model.
	 */
	public function hasRepositoryByName(string $name): bool;


	/**
	 * Returns repository by repository name.
	 */
	public function getRepositoryByName(string $name): IRepository;


	/**
	 * Returns true if repository class is attached to model.
	 */
	public function hasRepository(string $className): bool;


	/**
	 * Returns repository by repository class.
	 * @template T of IRepository
	 * @phpstan-param class-string<T> $className
	 * @phpstan-return T
	 */
	public function getRepository(string $className): IRepository;


	/**
	 * Returns repository associated for entity type.
	 * @param IEntity|string $entity
	 * @phpstan-param IEntity|class-string<IEntity> $entity
	 */
	public function getRepositoryForEntity($entity): IRepository;


	/**
	 * Returns entity metadata storage.
	 */
	public function getMetadataStorage(): MetadataStorage;


	/**
	 * Persist the entity with cascade.
	 * @template E of IEntity
	 * @phpstan-param E $entity
	 * @phpstan-return E
	 */
	public function persist(IEntity $entity, bool $withCascade = true): IEntity;


	/**
	 * Persist the entity with cascade and flushes the model.
	 * @template E of IEntity
	 * @phpstan-param E $entity
	 * @phpstan-return E
	 */
	public function persistAndFlush(IEntity $entity): IEntity;


	/**
	 * Removes the entity with cascade.
	 * @template E of IEntity
	 * @phpstan-param E $entity
	 * @phpstan-return E
	 */
	public function remove(IEntity $entity, bool $withCascade = true): IEntity;


	/**
	 * Removes the entity with cascade and flushes the model.
	 * @template E of IEntity
	 * @phpstan-param E $entity
	 * @phpstan-return E
	 */
	public function removeAndFlush(IEntity $entity, bool $withCascade = true): IEntity;


	/**
	 * Flushes all persisted changes in repositories.
	 */
	public function flush(): void;


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
