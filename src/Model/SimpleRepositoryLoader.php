<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Model;

use Nette\Object;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Repository\IRepository;


class SimpleRepositoryLoader extends Object implements IRepositoryLoader
{
	/** @var IRepository[] */
	private $repositories;


	/**
	 * @param IRepository[] $repositories
	 */
	public function __construct(array $repositories)
	{
		$this->repositories = [];
		foreach ($repositories as $repository) {
			$this->repositories[get_class($repository)] = $repository;
		}
	}


	public function hasRepository($className)
	{
		return isset($this->repositories[$className]);
	}


	public function getRepository($className)
	{
		if (!isset($this->repositories[$className])) {
			throw new InvalidArgumentException("Repository '$className' not defined.");
		}
		return $this->repositories[$className];
	}


	public function isCreated($className)
	{
		return TRUE;
	}

}
