<?php

namespace Nextras\Orm\Model;

use Nextras\Orm\Repository\IRepository;


interface IRepositoryLoader
{
	/**
	 * Returns true if repository exists.
	 * @param  string $className
	 * @return bool
	 */
	public function hasRepository($className);


	/**
	 * Returns instance of repository.
	 * @param  string   $className
	 * @return IRepository
	 */
	public function getRepository($className);


	/**
	 * Checks, if repository has been already created.
	 * @param  string   $className
	 * @return bool
	 */
	public function isCreated($className);
}
