<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper;

use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Repository\IRepository;


trait MapperRepositoryTrait
{
	/** @var IRepository|null */
	private $repository;


	public function setRepository(IRepository $repository): void
	{
		if ($this->repository !== null && $this->repository !== $repository) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is already attached to repository.");
		}

		$this->repository = $repository;
	}


	public function getRepository(): IRepository
	{
		if ($this->repository === null) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is not attached to repository.");
		}

		return $this->repository;
	}
}
