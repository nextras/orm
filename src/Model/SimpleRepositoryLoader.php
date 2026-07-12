<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IRepository;
use function array_values;
use function get_class;


/**
 * Loads repositories from a static list of already instantiated repositories.
 * All repositories are considered initialized right away.
 */
class SimpleRepositoryLoader implements IRepositoryLoader
{
	/** @var array<class-string<IRepository<*>>, IRepository<*>> */
	private array $repositories = [];

	/** @var array<string, class-string<IRepository<*>>> */
	private array $nameToClassNameMap = [];


	/**
	 * @param array<string, IRepository<*>> $repositories Map of a repository simple name and its instance. The name
	 * is used for accessing the repository by name in contrast to accessing it by its class-string.
	 * @param array<class-string<IEntity>, class-string<IRepository<IEntity>>> $entityClassNameToClassNameMap Map of an
	 * entity class name to the class name of the repository that manages it.
	 */
	public function __construct(
		array $repositories,
		private readonly array $entityClassNameToClassNameMap = [],
	)
	{
		foreach ($repositories as $name => $repository) {
			$className = get_class($repository);
			$this->repositories[$className] = $repository;
			$this->nameToClassNameMap[$name] = $className;
		}
	}


	#[\Override]
	public function hasRepository(string $className): bool
	{
		return isset($this->repositories[$className]);
	}


	#[\Override]
	public function hasRepositoryByName(string $name): bool
	{
		return isset($this->nameToClassNameMap[$name]);
	}


	/**
	 * @template T of IRepository<*>
	 * @param class-string<T> $className
	 * @return T|null
	 */
	#[\Override]
	public function getRepository(string $className): IRepository|null
	{
		/** @var T|null */
		return $this->repositories[$className] ?? null;
	}


	#[\Override]
	public function getRepositoryByName(string $name): IRepository|null
	{
		$className = $this->nameToClassNameMap[$name] ?? null;
		return $className !== null ? $this->repositories[$className] : null;
	}


	/**
	 * @template E of IEntity
	 * @param class-string<E> $entityClassName
	 * @return class-string<IRepository<E>>|null
	 */
	#[\Override]
	public function getRepositoryClassNameForEntity(string $entityClassName): string|null
	{
		/** @var class-string<IRepository<E>>|null */
		return $this->entityClassNameToClassNameMap[$entityClassName] ?? null;
	}


	#[\Override]
	public function getInitializedRepositories(): array
	{
		return array_values($this->repositories);
	}
}
