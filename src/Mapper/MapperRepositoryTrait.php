<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


use Nextras\Orm\Exception\InvalidStateException;
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
