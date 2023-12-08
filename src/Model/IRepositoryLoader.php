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
	 * Returns instance of repository.
	 * @template T of IRepository<IEntity>
	 * @param class-string<T> $className
	 * @return T
	 */
	public function getRepository(string $className): IRepository;


	/**
	 * Checks, if repository has been already created.
	 * @template T of IRepository<IEntity>
	 * @param class-string<T> $className
	 */
	public function isCreated(string $className): bool;
}
