<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nette\SmartObject;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\Repository\PersistenceHelper;
use Nextras\Orm\Repository\RemovalHelper;
use function array_keys;
use function array_merge;
use function get_class;
use function is_object;
use function is_string;


class Model implements IModel
{
	use SmartObject;

	/** @var list<callable(IEntity[] $persisted, IEntity[] $removed): void> */
	public $onFlush = [];


	/**
	 * Creates repository list configuration.
	 * @param  array<string, class-string<IRepository<IEntity>>|IRepository<IEntity>> $repositories
	 * @return array{
	 *     array<class-string<IRepository<IEntity>>, true>,
	 *     array<string, class-string<IRepository<IEntity>>>,
	 *     array<class-string<IEntity>, class-string<IRepository<IEntity>>>
	 *     }
	 */
	public static function getConfiguration(array $repositories): array
	{
		$config = [[], [], []];
		foreach ($repositories as $name => $repository) {
			/**
			 * @var class-string<IRepository<IEntity>> $className
			 */
			$className = is_object($repository) ? get_class($repository) : $repository;
			$config[0][$className] = true;
			$config[1][$name] = $className;
			foreach ($repository::getEntityClassNames() as $entityClassName) {
				$config[2][$entityClassName] = $className;
			}
		}
		return $config;
	}


	/**
	 * @param array{
	 *     array<class-string<IRepository<IEntity>>, true>,
	 *     array<string, class-string<IRepository<IEntity>>>,
	 *     array<class-string<IEntity>, class-string<IRepository<IEntity>>>
	 *     } $configuration
	 */
	public function __construct(
		private readonly array $configuration,
		private readonly IRepositoryLoader $repositoryLoader,
		private readonly MetadataStorage $metadataStorage
	)
	{
	}


	public function hasRepositoryByName(string $name): bool
	{
		return isset($this->configuration[1][$name]);
	}


	public function getRepositoryByName(string $name): IRepository
	{
		if (!isset($this->configuration[1][$name])) {
			throw new InvalidArgumentException("Repository '$name' does not exist.");
		}
		return $this->getRepository($this->configuration[1][$name]);
	}


	public function hasRepository(string $className): bool
	{
		return isset($this->configuration[0][$className]);
	}


	/**
	 * Returns repository by repository class.
	 * @template E of IEntity
	 * @template T of IRepository<E>
	 * @param class-string<T> $className
	 * @return T
	 */
	public function getRepository(string $className): IRepository
	{
		if (!isset($this->configuration[0][$className])) {
			throw new InvalidArgumentException("Repository '$className' does not exist.");
		}
		$repository = $this->repositoryLoader->getRepository($className);
		return $repository;
	}


	/**
	 * @template E of IEntity
	 * @param E|class-string<E> $entity
	 * @return IRepository<E>
	 */
	public function getRepositoryForEntity($entity): IRepository
	{
		$entityClassName = is_string($entity) ? $entity : get_class($entity);
		if (!isset($this->configuration[2][$entityClassName])) {
			throw new InvalidArgumentException("Repository for '$entityClassName' entity does not exist.");
		}
		/** @var IRepository<E> */
		return $this->getRepository($this->configuration[2][$entityClassName]);
	}


	public function getMetadataStorage(): MetadataStorage
	{
		return $this->metadataStorage;
	}


	public function persist(IEntity $entity, bool $withCascade = true): IEntity
	{
		$queue = PersistenceHelper::getCascadeQueue($entity, $this, $withCascade);
		foreach ($queue as $object) {
			if ($object instanceof IEntity) {
				$repository = $this->configuration[2][get_class($object)];
				$this->repositoryLoader->getRepository($repository)->doPersist($object);
			} elseif ($object instanceof IRelationshipCollection) {
				$object->doPersist();
			} elseif ($object instanceof IRelationshipContainer) {
				$object->doPersist();
			}
		}
		return $entity;
	}


	public function persistAndFlush(IEntity $entity): IEntity
	{
		$this->persist($entity);
		$this->flush();
		return $entity;
	}


	public function remove(IEntity $entity, bool $withCascade = true): IEntity
	{
		$queuePersist = $queueRemove = [];
		RemovalHelper::getCascadeQueueAndSetNulls($entity, $this, $withCascade, $queuePersist, $queueRemove);
		foreach ($queuePersist as $object) {
			if ($object instanceof IEntity) {
				$repository = $this->configuration[2][get_class($object)];
				$this->repositoryLoader->getRepository($repository)->doPersist($object);
			} elseif ($object instanceof IRelationshipCollection) {
				$object->doPersist();
			} elseif ($object instanceof IRelationshipContainer) {
				$object->doPersist();
			}
		}
		foreach ($queueRemove as $object) {
			$repository = $this->configuration[2][get_class($object)];
			$this->repositoryLoader->getRepository($repository)->doRemove($object);
		}
		return $entity;
	}


	public function removeAndFlush(IEntity $entity, bool $withCascade = true): IEntity
	{
		$this->remove($entity, $withCascade);
		$this->flush();
		return $entity;
	}


	public function flush(): void
	{
		$allPersisted = [];
		$allRemoved = [];
		foreach ($this->getLoadedRepositories() as $repository) {
			[$persisted, $removed] = $repository->doFlush();
			$allPersisted = array_merge($allPersisted, $persisted);
			$allRemoved = array_merge($allRemoved, $removed);
		}

		$this->onFlush($allPersisted, $allRemoved);
	}


	public function clear(): void
	{
		foreach ($this->getLoadedRepositories() as $repository) {
			$repository->doClear();
		}
	}


	public function refreshAll(bool $allowOverwrite = false): void
	{
		foreach ($this->getLoadedRepositories() as $repository) {
			$repository->doRefreshAll($allowOverwrite);
		}
	}


	/**
	 * Returns repository by name.
	 * @return IRepository<IEntity>
	 */
	public function &__get(string $name): IRepository
	{
		$repository = $this->getRepositoryByName($name);
		return $repository;
	}


	/**
	 * @return list<IRepository<IEntity>>
	 */
	private function getLoadedRepositories(): array
	{
		$repositories = [];
		foreach (array_keys($this->configuration[0]) as $className) {
			if ($this->repositoryLoader->isCreated($className)) {
				$repositories[] = $this->repositoryLoader->getRepository($className);
			}
		}

		return $repositories;
	}
}
