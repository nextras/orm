<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Memory;


use DateTimeImmutable;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\IOException;
use Nextras\Orm\Exception\LogicException;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Mapper\Memory\Conventions\Conventions;
use Nextras\Orm\Mapper\Memory\Conventions\IConventions;
use Nextras\Orm\Repository\IRepository;
use function array_keys;
use function array_values;
use function assert;


/**
 * @template E of IEntity
 * @implements IMapper<E>
 */
abstract class ArrayMapper implements IMapper
{
	/** @var array<int|string, mixed>|null */
	protected array|null $data = null;

	/** @var array<string, array<int|string, mixed>> */
	protected array $relationshipData = [];

	protected IConventions|null $conventions = null;

	/** @var IRepository<IEntity>|null */
	private IRepository|null $repository = null;

	/** @var resource|null */
	static protected $lock;


	public function setRepository(IRepository $repository): void
	{
		if ($this->repository !== null && $this->repository !== $repository) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is already attached to repository.");
		}

		$this->repository = $repository;
	}


	public function getRepository(): IRepository
	{
		if ($this->repository === null) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is not attached to repository.");
		}
		/** @var IRepository<E> */
		return $this->repository;
	}


	public function findAll(): ICollection
	{
		return new ArrayCollection($this->getData(), $this->getRepository());
	}


	/**
	 * @param list<E> $data
	 * @return ICollection<E>
	 */
	public function toCollection(array $data): ICollection
	{
		return new ArrayCollection($data, $this->getRepository());
	}


	public function createCollectionManyHasOne(PropertyMetadata $metadata): ICollection
	{
		$collection = $this->findAll();
		$collection->setRelationshipMapper(new RelationshipMapperManyHasOne($metadata));
		return $collection;
	}


	public function createCollectionOneHasOne(PropertyMetadata $metadata): ICollection
	{
		assert($metadata->relationship !== null);
		$collection = $this->findAll();
		$collection->setRelationshipMapper(
			$metadata->relationship->isMain
				? new RelationshipMapperManyHasOne($metadata)
				: new RelationshipMapperOneHasOne($this, $metadata)
		);
		return $collection;
	}


	public function createCollectionManyHasMany(IMapper $sourceMapper, PropertyMetadata $metadata): ICollection
	{
		assert($sourceMapper instanceof ArrayMapper);
		$collection = $this->findAll();
		$collection->setRelationshipMapper(new RelationshipMapperManyHasMany($this, $sourceMapper, $metadata));
		return $collection;
	}


	public function createCollectionOneHasMany(PropertyMetadata $metadata): ICollection
	{
		$collection = $this->findAll();
		$collection->setRelationshipMapper(new RelationshipMapperOneHasMany($this, $metadata));
		return $collection;
	}


	public function clearCache(): void
	{
		$this->data = null;
	}


	/**
	 * @return array<int|string, mixed>
	 */
	public function &getRelationshipDataStorage(string $key): array
	{
		$value = &$this->relationshipData[$key];
		$value = (array) $value; // @phpstan-ignore-line
		return $value;
	}


	public function persist(IEntity $entity): void
	{
		$this->initializeData();
		assert($this->data !== null);

		$data = $this->entityToArray($entity);
		$data = $this->getConventions()->convertEntityToStorage($data);

		if ($entity->isPersisted()) {
			$id = $entity->getPersistedId();
			$primaryValue = $this->getIdHash($id);
		} elseif ($entity->hasValue('id')) {
			$id = $entity->getValue('id');
			$primaryValue = $this->getIdHash($id);
			if (isset($this->data[$primaryValue])) {
				throw new InvalidStateException("Unique constraint violation: entity with '$primaryValue' primary value already exists.");
			}
		} else {
			$ids = array_keys($this->data);
			$id = count($ids) > 0 ? ((int) max($ids)) + 1 : 1;
			$primaryValue = $this->getIdHash($id);
			$storagePrimaryKey = $this->getConventions()->getStoragePrimaryKey();
			$data[$storagePrimaryKey[0]] = $id;
		}

		$this->data[$primaryValue] = $data;
		$entity->onPersist($id);
	}


	public function remove(IEntity $entity): void
	{
		$this->initializeData();
		assert($this->data !== null);

		$id = $this->getIdHash($entity->getPersistedId());
		$this->data[$id] = null;
	}


	public function flush(): void
	{
		try {
			$this->lock();
			if ($this->data === null) {
				return;
			}
			$this->saveData([$this->data, $this->relationshipData]);
		} finally {
			$this->unlock();
		}
	}


	public function rollback(): void
	{
		$this->data = null;
	}


	public function getConventions(): IConventions
	{
		if ($this->conventions === null) {
			$this->conventions = $this->createConventions();
		}

		return $this->conventions;
	}


	protected function createConventions(): IConventions
	{
		return new Conventions($this->getRepository()->getEntityMetadata()->getPrimaryKey());
	}


	protected function initializeData(): void
	{
		if ($this->data !== null) {
			return;
		}

		$stored = $this->readData();
		$this->data = $stored[0] ?? [];
		$this->relationshipData = $stored[1] ?? [];
	}


	/**
	 * @return list<E>
	 */
	protected function getData(): array
	{
		$this->initializeData();
		assert($this->data !== null);

		$repository = $this->getRepository();
		$conventions = $this->getConventions();

		$entities = [];
		foreach ($this->data as $row) {
			if ($row === null) continue;
			$entity = $repository->hydrateEntity($conventions->convertStorageToEntity($row));
			if ($entity !== null) { // entity may have been deleted
				$idHash = $this->getIdHash($entity->getPersistedId());
				$entities[$idHash] = $entity;
			}
		}

		return array_values($entities);
	}


	protected function lock(): void
	{
		if (self::$lock !== null) {
			throw new LogicException('Critical section has already beed entered.');
		}

		$file = realpath(sys_get_temp_dir()) . '/NextrasOrmArrayMapper.lock.' . md5(__FILE__);
		$handle = fopen($file, 'c+');
		if ($handle === false) {
			throw new IOException('Unable to create critical section.');
		}

		flock($handle, LOCK_EX);
		self::$lock = $handle;
	}


	protected function unlock(): void
	{
		if (self::$lock === null) {
			throw new LogicException('Critical section has not been initialized.');
		}

		flock(self::$lock, LOCK_UN);
		fclose(self::$lock);
		self::$lock = null;
	}


	/**
	 * @return array<string, mixed>
	 */
	protected function entityToArray(IEntity $entity): array
	{
		return $entity->getRawValues(/* $modifiedOnly = */ false);
	}


	/**
	 * @param mixed $id
	 */
	protected function getIdHash($id): string
	{
		if (!is_array($id)) {
			return $id instanceof DateTimeImmutable
				? $id->format('c.u')
				: (string) $id;
		}

		return implode(
			',',
			array_map(
				function ($id): string {
					return $id instanceof DateTimeImmutable
						? $id->format('c.u')
						: (string) $id;
				},
				$id
			)
		);
	}


	/**
	 * Reads stored data
	 * @return array{
	 *      0?: array<int|string, mixed>,
	 *      1?: array<string, array<int|string, mixed>>
	 * }
	 */
	abstract protected function readData(): array;


	/**
	 * Stores data
	 * @param array{
	 *      0?: array<int|string, mixed>,
	 *      1?: array<string, array<int|string, mixed>>
	 * } $data
	 */
	abstract protected function saveData(array $data): void;
}
