<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\DI\Container;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Model\IRepositoryLoader;
use Nextras\Orm\Repository\IRepository;


class RepositoryLoader implements IRepositoryLoader
{
	/** @var Container */
	private $container;

	/** @var array<class-string<IRepository<\Nextras\Orm\Entity\IEntity>>, string> */
	private $repositoryNamesMap;


	/**
	 * @param array<class-string<IRepository<\Nextras\Orm\Entity\IEntity>>, string> $repositoryNamesMap
	 */
	public function __construct(Container $container, array $repositoryNamesMap)
	{
		$this->container = $container;
		$this->repositoryNamesMap = $repositoryNamesMap;
	}


	public function hasRepository(string $className): bool
	{
		return isset($this->repositoryNamesMap[$className]);
	}


	/**
	 * Returns instance of repository.
	 * @phpstan-template R of IRepository<\Nextras\Orm\Entity\IEntity>
	 * @phpstan-param class-string<R> $className
	 * @phpstan-return R
	 */
	public function getRepository(string $className): IRepository
	{
		/** @phpstan-var R */
		return $this->container->getService($this->repositoryNamesMap[$className]);
	}


	public function isCreated(string $className): bool
	{
		return $this->container->isCreated($this->repositoryNamesMap[$className]);
	}
}
