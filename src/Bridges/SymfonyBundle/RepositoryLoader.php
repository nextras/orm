<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\SymfonyBundle;

use Nextras;
use Nextras\Orm\Repository\IRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;


class RepositoryLoader implements Nextras\Orm\Model\IRepositoryLoader
{
	/** @var ContainerInterface */
	private $container;


	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}


	public function hasRepository(string $className): bool
	{
		return $this->container->has($className);
	}


	/**
	 * @phpstan-template R of IRepository<\Nextras\Orm\Entity\IEntity>
	 * @phpstan-param class-string<R> $className
	 * @phpstan-return R
	 */
	public function getRepository(string $className): IRepository
	{
		/** @phpstan-var R */
		return $this->container->get($className);
	}


	public function isCreated(string $className): bool
	{
		return $this->container->initialized($className);
	}
}
