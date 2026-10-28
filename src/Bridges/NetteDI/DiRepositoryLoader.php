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
	public function __construct(
		private readonly Container $container,
		/** @var array<class-string<IRepository<IEntity>>, string> */
		private readonly array $repositoryClassNameToDiNameMap,
		/** @var array<string, string> */
		private readonly array $repositoryNameToDiNameMap,
		/** @var array<class-string<IEntity>, class-string<IRepository<*>>> */
		private readonly array $entityClassNameToRepositoryClassNameMap,
	)
	{
	}


	#[\Override]
	#[\NoDiscard]
	public function hasRepository(string $className): bool
	{
		return isset($this->repositoryClassNameToDiNameMap[$className]);
	}


	#[\Override]
	#[\NoDiscard]
	public function hasRepositoryByName(string $name): bool
	{
		return isset($this->repositoryNameToDiNameMap[$name]);
	}


	#[\Override]
	#[\NoDiscard]
	public function getRepository(string $className): IRepository|null
	{
		return $this->container->getService($this->repositoryClassNameToDiNameMap[$className]);
	}


	#[\Override]
	#[\NoDiscard]
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
	#[\NoDiscard]
	public function getRepositoryClassNameForEntity(string $entityClassName): string|null
	{
		/** @var class-string<IRepository<E>>|null */
		return $this->entityClassNameToRepositoryClassNameMap[$entityClassName] ?? null;
	}


	#[\Override]
	#[\NoDiscard]
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
