<?php declare(strict_types = 1);

namespace Nextras\Orm\Model;


use Nette\SmartObject;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\NotSupportedException;
use Nextras\Orm\Exception\RollbackException;
use Nextras\Orm\Mapper\IRollbackableMapper;
use Nextras\Orm\Mapper\ITransactionalMapper;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\Repository\PersistenceHelper;
use Nextras\Orm\Repository\PersistenceMode;
use Throwable;
use function array_merge;
use function is_string;


class Model implements IModel
{
	use SmartObject;

	/** @var list<callable(IEntity[] $persisted, IEntity[] $removed): void> */
	public array $onFlush = [];

	private bool $transactionActive = false;

	private Throwable|null $transactionFailure = null;


	public function __construct(
		private readonly IRepositoryLoader $repositoryLoader,
		private readonly MetadataStorage $metadataStorage,
	)
	{
	}


	#[\NoDiscard]
	public function hasRepositoryByName(string $name): bool
	{
		return $this->repositoryLoader->hasRepositoryByName($name);
	}


	#[\NoDiscard]
	public function getRepositoryByName(string $name): IRepository
	{
		return $this->repositoryLoader->getRepositoryByName($name)
			?? throw new InvalidArgumentException("Repository with '$name' simple name does not exist.");
	}


	#[\NoDiscard]
	public function hasRepository(string $className): bool
	{
		return $this->repositoryLoader->hasRepository($className);
	}


	#[\NoDiscard]
	public function getRepository(string $className): IRepository
	{
		return $this->repositoryLoader->getRepository($className)
			?? throw new InvalidArgumentException("Repository with '$className' class name does not exist.");
	}


	#[\NoDiscard]
	public function getRepositoryForEntity($entity): IRepository
	{
		$entityClassName = is_string($entity) ? $entity : $entity::class;
		$repositoryClassName = $this->repositoryLoader->getRepositoryClassNameForEntity($entityClassName)
			?? throw new InvalidArgumentException("No repository manages '$entityClassName' entity.");
		return $this->getRepository($repositoryClassName);
	}


	#[\NoDiscard]
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
		if ($this->transactionFailure !== null) {
			throw new InvalidStateException('The transaction has already failed.', 0, $this->transactionFailure);
		}
		try {
			$allPersisted = [];
			$allRemoved = [];
			foreach ($this->repositoryLoader->getInitializedRepositories() as $repository) {
				[$persisted, $removed] = $repository->doFlush($this->transactionActive);
				$allPersisted = array_merge($allPersisted, $persisted);
				$allRemoved = array_merge($allRemoved, $removed);
			}

			$this->onFlush($allPersisted, $allRemoved);

		} catch (Throwable $e) {
			if ($this->transactionActive) {
				$this->transactionFailure = $e;
			}
			throw $e;
		}
	}


	public function rollback(): void
	{
		if ($this->transactionActive) {
			throw new InvalidStateException('Cannot call rollback() inside transactional(). Throw an exception instead.');
		}
		$errors = [];
		$participants = [];
		foreach ($this->repositoryLoader->getInitializedRepositories() as $repository) {
			try {
				$mapper = $repository->getMapper();
				if ($mapper instanceof ITransactionalMapper) {
					$id = spl_object_id($mapper->getTransactionCoordinator());
					if (isset($participants[$id])) {
						continue;
					}
					$participants[$id] = true;
				}
				if (!$mapper instanceof IRollbackableMapper) {
					throw new NotSupportedException('Mapper ' . $mapper::class . ' does not support rollback.');
				}
				$mapper->rollback();
			} catch (Throwable $e) {
				$errors[] = $e;
			}
		}
		$errors = array_merge($errors, $this->resetRepositories());
		if ($errors !== []) {
			throw new RollbackException($errors);
		}
	}


	public function transactional(callable $callback): mixed
	{
		if ($this->transactionActive) {
			throw new InvalidStateException('Nested Model::transactional() calls are not supported.');
		}
		$coordinator = null;
		foreach ($this->repositoryLoader->getRepositories() as $repository) {
			$mapper = $repository->getMapper();
			if (!$mapper instanceof ITransactionalMapper) {
				throw new NotSupportedException('Mapper ' . $mapper::class . ' does not support transaction callbacks.');
			}
			$participant = $mapper->getTransactionCoordinator();
			if ($coordinator !== null && $participant !== $coordinator) {
				throw new NotSupportedException('Model::transactional() requires all mappers to share one transaction coordinator.');
			}
			$coordinator = $participant;
		}
		if ($coordinator === null) {
			throw new NotSupportedException('Model::transactional() requires at least one transactional mapper.');
		}

		$this->transactionActive = true;
		$started = false;
		try {
			$result = $coordinator->transactional(function () use ($callback, &$started) {
				$started = true;
				$result = $callback($this);
				if ($this->transactionFailure !== null) {
					throw $this->transactionFailure;
				}
				$this->flush();
				return $result;
			});
		} catch (Throwable $e) {
			if ($started) {
				$errors = $this->resetRepositories();
				if ($errors !== []) {
					throw new RollbackException($errors, $e);
				}
			}
			throw $e;
		} finally {
			$this->transactionActive = false;
			$this->transactionFailure = null;
		}
		foreach ($this->repositoryLoader->getInitializedRepositories() as $completedRepository) {
			$completedRepository->doFinalizeTransaction();
		}
		return $result;
	}


	/**
	 * @return list<Throwable>
	 */
	private function resetRepositories(): array
	{
		$errors = [];
		foreach ($this->repositoryLoader->getInitializedRepositories() as $repository) {
			try {
				$repository->doClear();
			} catch (Throwable $e) {
				$errors[] = $e;
			}
		}
		return $errors;
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
		if ($this->transactionFailure !== null) {
			throw new InvalidStateException('The transaction has already failed.', 0, $this->transactionFailure);
		}
		try {
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
		} catch (Throwable $e) {
			if ($this->transactionActive) {
				$this->transactionFailure = $e;
			}
			throw $e;
		}
	}
}
