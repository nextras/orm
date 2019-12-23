<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Repository\IRepository;


class SimpleRepositoryLoader implements IRepositoryLoader
{
	/**
	 * @var IRepository[]
	 * @phpstan-var array<class-string<IRepository<\Nextras\Orm\Entity\IEntity>>, IRepository<\Nextras\Orm\Entity\IEntity>>
	 */
	private $repositories;


	/**
	 * @phpstan-param list<IRepository<\Nextras\Orm\Entity\IEntity>> $repositories
	 */
	public function __construct(array $repositories)
	{
		$this->repositories = [];
		foreach ($repositories as $repository) {
			$this->repositories[get_class($repository)] = $repository;
		}
	}


	/** {@inheritDoc} */
	public function hasRepository(string $className): bool
	{
		return isset($this->repositories[$className]);
	}


	/**
	 * Returns instance of repository.
	 * @phpstan-template T of IRepository<\Nextras\Orm\Entity\IEntity>
	 * @phpstan-param class-string<T> $className
	 * @phpstan-return T
	 */
	public function getRepository(string $className): IRepository
	{
		if (!isset($this->repositories[$className])) {
			throw new InvalidArgumentException("Repository '$className' not defined.");
		}
		/** @phpstan-var T */
		return $this->repositories[$className];
	}


	public function isCreated(string $className): bool
	{
		return true;
	}
}
