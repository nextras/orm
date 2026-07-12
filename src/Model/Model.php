<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nette\SmartObject;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\Repository\PersistenceHelper;
use Nextras\Orm\Repository\PersistenceMode;
use function array_merge;
use function get_class;
use function is_string;


class Model implements IModel
{
	use SmartObject;

	/** @var list<callable(IEntity[] $persisted, IEntity[] $removed): void> */
	public array $onFlush = [];


	public function __construct(
		private readonly IRepositoryLoader $repositoryLoader,
		private readonly MetadataStorage $metadataStorage
	)
	{
	}


	public function hasRepositoryByName(string $name): bool
	{
		return $this->repositoryLoader->hasRepositoryByName($name);
	}


	public function getRepositoryByName(string $name): IRepository
	{
		return $this->repositoryLoader->getRepositoryByName($name)
			?? throw new InvalidArgumentException("Repository with '$name' simple name does not exist.");
	}


	public function hasRepository(string $className): bool
	{
		return $this->repositoryLoader->hasRepository($className);
	}


	public function getRepository(string $className): IRepository
	{
		return $this->repositoryLoader->getRepository($className)
			?? throw new InvalidArgumentException("Repository with '$className' class name does not exist.");
	}


	public function getRepositoryForEntity($entity): IRepository
	{
		$entityClassName = is_string($entity) ? $entity : get_class($entity);
		$repositoryClassName = $this->repositoryLoader->getRepositoryClassNameForEntity($entityClassName)
			?? throw new InvalidArgumentException("No repository manages '$entityClassName' entity.");
		return $this->getRepository($repositoryClassName);
	}


	public function getMetadataStorage(): MetadataStorage
	{
		return $this->metadataStorage;
	}


	public function persist(IEntity $entity, bool $withCascade = true): IEntity
	{
		$this->processPersist(mode: PersistenceMode::Persist, entity: $entity, withCascade: $withCascade);
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
		$this->processPersist(mode: PersistenceMode::Remove, entity: $entity, withCascade: $withCascade);
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
		foreach ($this->repositoryLoader->getInitializedRepositories() as $repository) {
			[$persisted, $removed] = $repository->doFlush();
			$allPersisted = array_merge($allPersisted, $persisted);
			$allRemoved = array_merge($allRemoved, $removed);
		}

		$this->onFlush($allPersisted, $allRemoved);
	}


	public function clear(): void
	{
		foreach ($this->repositoryLoader->getInitializedRepositories() as $repository) {
			$repository->doClear();
		}
	}


	public function refreshAll(bool $allowOverwrite = false): void
	{
		foreach ($this->repositoryLoader->getInitializedRepositories() as $repository) {
			$repository->doRefreshAll($allowOverwrite);
		}
	}


	/**
	 * Returns repository by its name.
	 * @return IRepository<*>
	 */
	public function &__get(string $name): IRepository
	{
		$repository = $this->getRepositoryByName($name);
		return $repository;
	}


	protected function processPersist(PersistenceMode $mode, IEntity $entity, bool $withCascade): void
	{
		[$queuePersist, $queueRemove] = PersistenceHelper::getCascadeQueue($entity, $mode, $this, $withCascade);
		foreach ($queuePersist as $object) {
			if ($object instanceof IEntity) {
				$repository = $this->getRepositoryForEntity($object);
				$repository->doPersist($object);
			} elseif ($object instanceof IRelationshipCollection) {
				$object->doPersist();
			} elseif ($object instanceof IRelationshipContainer) {
				$object->doPersist();
			}
		}
		foreach ($queueRemove as $object) {
			$repository = $this->getRepositoryForEntity($object);
			$repository->doRemove($object);
		}
	}
}
