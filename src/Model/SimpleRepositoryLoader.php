<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Repository\IRepository;


class SimpleRepositoryLoader implements IRepositoryLoader
{
	/** @var array<class-string<IRepository<IEntity>>, IRepository<IEntity>> */
	private array $repositories = [];


	/**
	 * @param list<IRepository<IEntity>> $repositories
	 */
	public function __construct(array $repositories)
	{
		foreach ($repositories as $repository) {
			$this->repositories[get_class($repository)] = $repository;
		}
	}


	public function hasRepository(string $className): bool
	{
		return isset($this->repositories[$className]);
	}


	/**
	 * Returns instance of repository.
	 * @template T of IRepository<IEntity>
	 * @param class-string<T> $className
	 * @return T
	 */
	public function getRepository(string $className): IRepository
	{
		if (!isset($this->repositories[$className])) {
			throw new InvalidArgumentException("Repository '$className' not defined.");
		}
		/** @var T */
		return $this->repositories[$className];
	}


	public function isCreated(string $className): bool
	{
		return true;
	}
}
