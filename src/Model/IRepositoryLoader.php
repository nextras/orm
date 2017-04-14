<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;

use Nextras\Orm\Repository\IRepository;


interface IRepositoryLoader
{
	/**
	 * Returns true if repository exists.
	 */
	public function hasRepository(string $className): bool;


	/**
	 * Returns instance of repository.
	 */
	public function getRepository(string $className): IRepository;


	/**
	 * Checks, if repository has been already created.
	 */
	public function isCreated(string $className): bool;
}
