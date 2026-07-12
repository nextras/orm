<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\Container;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Model\IRepositoryLoader;
use Nextras\Orm\Repository\IRepository;


/**
 * Loads repositories from Nette DI Container.
 * It is {@see OrmExtension} responsibility to register all needed relevant repositories to the Nette DI.
 */
class DiRepositoryLoader implements IRepositoryLoader
{
	/**
	 * @param array<class-string<IRepository<IEntity>>, string> $repositoryClassNameToDiNameMap
	 * @param array<string, string> $repositoryNameToDiNameMap
	 * @param array<class-string<IEntity>, class-string<IRepository<*>>> $entityClassNameToRepositoryClassNameMap
	 */
	public function __construct(
		private readonly Container $container,
		private readonly array $repositoryClassNameToDiNameMap,
		private readonly array $repositoryNameToDiNameMap,
		private readonly array $entityClassNameToRepositoryClassNameMap,
	)
	{
	}


	#[\Override]
	public function hasRepository(string $className): bool
	{
		return isset($this->repositoryClassNameToDiNameMap[$className]);
	}


	#[\Override]
	public function hasRepositoryByName(string $name): bool
	{
		return isset($this->repositoryNameToDiNameMap[$name]);
	}


	#[\Override]
	public function getRepository(string $className): IRepository|null
	{
		return $this->container->getService($this->repositoryClassNameToDiNameMap[$className]);
	}


	#[\Override]
	public function getRepositoryByName(string $name): IRepository|null
	{
		return $this->container->getService($this->repositoryNameToDiNameMap[$name]);
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
		return $this->entityClassNameToRepositoryClassNameMap[$entityClassName] ?? null;
	}


	#[\Override]
	public function getInitializedRepositories(): array
	{
		$repositories = [];
		foreach ($this->repositoryClassNameToDiNameMap as $diName) {
			if ($this->container->isCreated($diName)) {
				$repositories[] = $this->container->getService($diName);
			}
		}
		return $repositories;
	}
}
