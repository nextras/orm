<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\Container;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Extension;
use Nextras\Orm\Model\IRepositoryLoader;
use Nextras\Orm\Repository\IRepository;


class RepositoryLoader implements IRepositoryLoader
{
	/** @var array<string, true> */
	private array $configuredRepositories = [];


	/**
	 * @param array<class-string<IRepository<IEntity>>, string> $repositoryNamesMap
	 * @param list<Extension> $extensions
	 */
	public function __construct(
		private readonly Container $container,
		private readonly array $repositoryNamesMap,
		private readonly array $extensions,
	)
	{
	}


	public function hasRepository(string $className): bool
	{
		return isset($this->repositoryNamesMap[$className]);
	}


	/**
	 * Returns instance of repository.
	 * @template R of IRepository<IEntity>
	 * @param class-string<R> $className
	 * @return R
	 */
	public function getRepository(string $className): IRepository
	{
		/** @var R */
		$repository = $this->container->getService($this->repositoryNamesMap[$className]);

		if (!isset($this->configuredRepositories[$className])) {
			$this->configuredRepositories[$className] = true;
			foreach ($this->extensions as $extensions) {
				$extensions->configureRepository($repository);
				$extensions->configureMapper($repository->getMapper());
			}
		}

		return $repository;
	}


	public function isCreated(string $className): bool
	{
		return $this->container->isCreated($this->repositoryNamesMap[$className]);
	}
}
