<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IRepository;


interface IRepositoryLoader
{
	/**
	 * Returns true if repository exists.
	 * @param class-string<IRepository<IEntity>> $className
	 */
	public function hasRepository(string $className): bool;


	/**
	 * Returns true if repository exists with the simple $name.
	 * Repository may not be registered with a name. Class name is implicitly always present, though.
	 */
	public function hasRepositoryByName(string $name): bool;

	/**
	 * Returns instance of repository or null if it does not exist or is not registered into the Model and its loader.
	 *
	 * @template T of IRepository<*>
	 * @param class-string<T> $className
	 * @return T|null
	 */
	public function getRepository(string $className): IRepository|null;

	/**
	 * Returns instance of repository or null if it does not exist or is not registered into the Model and its loader
	 * via the $name.
	 *
	 * @return IRepository<*>|null
	 */
	public function getRepositoryByName(string $name): IRepository|null;


	/**
	 * Returns class name of entity's repository or null if no repository manages such entity.
	 * @template E of IEntity
	 * @param class-string<E> $entityClassName
	 * @return class-string<IRepository<E>>|null
	 */
	public function getRepositoryClassNameForEntity(string $entityClassName): string|null;


	/**
	 * Returns list of loaded (already initialized) repositories.
	 * @return list<IRepository<*>>
	 */
	public function getInitializedRepositories(): array;
}
