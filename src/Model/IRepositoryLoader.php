<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nextras\Orm\Repository\IRepository;


interface IRepositoryLoader
{
	/**
	 * Returns true if repository exists.
	 * @phpstan-param class-string<IRepository<\Nextras\Orm\Entity\IEntity>> $className
	 */
	public function hasRepository(string $className): bool;


	/**
	 * Returns instance of repository.
	 * @phpstan-template T of IRepository<\Nextras\Orm\Entity\IEntity>
	 * @phpstan-param class-string<T> $className
	 * @phpstan-return T
	 */
	public function getRepository(string $className): IRepository;


	/**
	 * Checks, if repository has been already created.
	 * @phpstan-template T of IRepository<\Nextras\Orm\Entity\IEntity>
	 * @phpstan-param class-string<T> $className
	 */
	public function isCreated(string $className): bool;
}
