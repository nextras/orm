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
use Nextras\Orm\Mapper\MapperRepositoryTrait;
use Nextras\Orm\Mapper\Memory\Conventions\Conventions;
use Nextras\Orm\Mapper\Memory\Conventions\IConventions;
use function array_values;
use function assert;


abstract class ArrayMapper implements IMapper
{
	use MapperRepositoryTrait;


	/**
	 * @var IEntity[]|null[]|null
	 * @phpstan-var array<int|string, IEntity|null>|null
	 */
	protected $data;

	/**
	 * @var array
	 * @phpstan-var array<int|string, array<string, mixed>|null>
	 */
	protected $dataToStore = [];

	/**
	 * @var array
	 * @phpstan-var array<string, array<int|string, mixed>>
	 */
	protected $relationshipData = [];

	/** @var IConventions */
	protected $conventions;

	/** @var resource|null */
	static protected $lock;


	public function findAll(): ICollection
	{
		return new ArrayCollection($this->getData(), $this->getRepository());
	}


	/**
	 * @phpstan-param list<IEntity> $data
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
	 * @phpstan-return array<int|string, mixed>
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

		$data = $this->entityToArray($entity);
		$data = $this->getConventions()->convertEntityToStorage($data);

		if ($entity->isPersisted()) {
			$id = $entity->getPersistedId();
			$primaryValue = $this->getIdHash($id);
		} else {
			$this->lock();
			try {
				$storedData = $this->readEntityData();
				if (!$entity->hasValue('id')) {
					$id = count($storedData) > 0 ? ((int) max(array_keys($storedData))) + 1 : 1;
					$storagePrimaryKey = $this->getConventions()->getStoragePrimaryKey();
					$data[$storagePrimaryKey[0]] = $id;
				} else {
					$id = $entity->getValue('id');
				}
				$primaryValue = $this->getIdHash($id);
				if (isset($storedData[$primaryValue])) {
					throw new InvalidStateException("Unique constraint violation: entity with '$primaryValue' primary value already exists.");
				}
				$storedData[$primaryValue] = null;
				$this->saveEntityData($storedData);
			} finally {
				$this->unlock();
			}
		}

		$this->data[$primaryValue] = $entity;
		$this->dataToStore[$primaryValue] = $data;

		$entity->onPersist($id);
	}


	public function remove(IEntity $entity): void
	{
		$this->initializeData();
		$id = $this->getIdHash($entity->getPersistedId());
		$this->data[$id] = null;
		$this->dataToStore[$id] = null;
	}


	public function flush(): void
	{
		$storageData = $this->readEntityData();
		foreach ($this->dataToStore as $id => $data) {
			$storageData[$id] = $data;
		}
		$this->saveEntityData($storageData);
		$this->dataToStore = [];
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

		$repository = $this->getRepository();
		$data = $this->readEntityData();

		$this->data = [];
		$conventions = $this->getConventions();
		foreach ($data as $row) {
			if ($row === null) {
				// auto increment placeholder
				continue;
			}

			$entity = $repository->hydrateEntity($conventions->convertStorageToEntity($row));
			if ($entity !== null) { // entity may have been deleted
				$idHash = $this->getIdHash($entity->getPersistedId());
				$this->data[$idHash] = $entity;
			}
		}
	}


	/**
	 * @phpstan-return list<IEntity>
	 */
	protected function getData(): array
	{
		$this->initializeData();
		assert($this->data !== null);
		return array_values(array_filter($this->data));
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
	 * @return array<int|string, mixed>
	 */
	protected function readEntityData(): array
	{
		// @phpstan-ignore-next-line https://github.com/phpstan/phpstan/issues/3357
		[$data, $relationshipData] = $this->readData() ?: [[], []];
		if ($this->relationshipData === []) {
			$this->relationshipData = $relationshipData;
		}
		return $data;
	}


	/**
	 * @phpstan-param array<int|string, mixed> $data
	 */
	protected function saveEntityData(array $data): void
	{
		$this->saveData([$data, $this->relationshipData]);
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
	 * @phpstan-return array{
	 *      0?: array<int|string, mixed>,
	 *      1?: array<string, array<int|string, mixed>>
	 * }
	 */
	abstract protected function readData(): array;


	/**
	 * Stores data
	 * @phpstan-param array{
	 *      array<int|string, mixed>,
	 *      array<string, array<int|string, mixed>>
	 * } $data
	 */
	abstract protected function saveData(array $data): void;
}
