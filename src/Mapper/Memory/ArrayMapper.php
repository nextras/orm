<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Memory;

use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\IOException;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\BaseMapper;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\StorageReflection\CommonReflection;


abstract class ArrayMapper extends BaseMapper
{
	/** @var IEntity[]|null[]|null */
	protected $data;

	/** @var array */
	protected $dataToStore = [];

	/** @var array */
	protected $relationshipData = [];

	/** @var resource|null */
	static protected $lock;


	public function findAll(): ICollection
	{
		return new ArrayCollection($this->getData(), $this->getRepository());
	}


	public function toCollection($data): ICollection
	{
		if (!is_array($data)) {
			throw new InvalidArgumentException("ArrayMapper can convert only array to ICollection.");
		}
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


	public function createCollectionManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata): ICollection
	{
		assert($metadata->relationship !== null);
		$targetMapper = $metadata->relationship->isMain ? $mapperTwo : $this;
		$collection = $targetMapper->findAll();
		$collection->setRelationshipMapper(new RelationshipMapperManyHasMany($metadata, $this));
		return $collection;
	}


	public function createCollectionOneHasMany(PropertyMetadata $metadata): ICollection
	{
		$collection = $this->findAll();
		$collection->setRelationshipMapper(new RelationshipMapperOneHasMany($this, $metadata));
		return $collection;
	}


	public function clearCache()
	{
		$this->data = null;
	}


	public function &getRelationshipDataStorage($key)
	{
		$value = & $this->relationshipData[$key];
		$value = (array) $value;
		return $value;
	}


	public function persist(IEntity $entity)
	{
		$this->initializeData();

		$data = $this->entityToArray($entity);
		$data = $this->getStorageReflection()->convertEntityToStorage($data);

		if ($entity->isPersisted()) {
			$id = $entity->getPersistedId();
			$primaryValue = $this->getIdHash($id);

		} else {
			$this->lock();
			try {
				$storedData = $this->readEntityData();
				if (!$entity->hasValue('id')) {
					$id = $storedData ? ((int) max(array_keys($storedData))) + 1 : 1;
					$storagePrimaryKey = $this->storageReflection->getStoragePrimaryKey();
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
		return $id;
	}


	public function remove(IEntity $entity)
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


	public function rollback()
	{
		$this->data = null;
	}


	protected function createStorageReflection()
	{
		return new CommonReflection(
			$this,
			$this->getTableName(),
			$this->getRepository()->getEntityMetadata()->getPrimaryKey()
		);
	}


	protected function initializeData()
	{
		if ($this->data !== null) {
			return;
		}

		$repository = $this->getRepository();
		$data = $this->readEntityData();

		$this->data = [];
		$storageReflection = $this->getStorageReflection();
		foreach ($data as $row) {
			if ($row === null) {
				// auto increment placeholder
				continue;
			}

			$entity = $repository->hydrateEntity($storageReflection->convertStorageToEntity($row));
			if ($entity !== null) { // entity may have been deleted
				$idHash = $this->getIdHash($entity->getPersistedId());
				$this->data[$idHash] = $entity;
			}
		}
	}


	protected function getData()
	{
		$this->initializeData();
		assert($this->data !== null);
		return array_filter($this->data);
	}


	protected function lock()
	{
		if (self::$lock) {
			throw new LogicException('Critical section has already beed entered.');
		}

		$file = realpath(sys_get_temp_dir()) . '/NextrasOrmArrayMapper.lock.' . md5(__FILE__);
		$handle = fopen($file, 'c+');
		if (!$handle) {
			throw new IOException('Unable to create critical section.');
		}

		flock($handle, LOCK_EX);
		self::$lock = $handle;
	}


	protected function unlock()
	{
		if (!self::$lock) {
			throw new LogicException('Critical section has not been initialized.');
		}

		flock(self::$lock, LOCK_UN);
		fclose(self::$lock);
		self::$lock = null;
	}


	protected function entityToArray(IEntity $entity): array
	{
		$return = [];
		$metadata = $entity->getMetadata();

		foreach ($metadata->getProperties() as $name => $metadataProperty) {
			if ($metadataProperty->isVirtual) {
				continue;
			} elseif ($metadataProperty->isPrimary && !$entity->hasValue($name)) {
				continue;
			}

			$property = $entity->getProperty($name);
			if ($property instanceof IRelationshipCollection) {
				continue;
			}

			if ($property instanceof IProperty) {
				$return = $property->saveValue($entity, $return);
			} else {
				$return[$name] = $entity->getValue($name);
			}
		}

		return $return;
	}


	protected function readEntityData()
	{
		[$data, $relationshipData] = $this->readData() ?: [[], []];
		if (!$this->relationshipData) {
			$this->relationshipData = $relationshipData;
		}
		return $data;
	}


	protected function saveEntityData(array $data)
	{
		$this->saveData([$data, $this->relationshipData]);
	}


	protected function getIdHash($id): string
	{
		if (!is_array($id)) {
			return $id instanceof \DateTimeImmutable
				? $id->format('c.u')
				: (string) $id;
		}

		return implode(
			',',
			array_map(
				function ($id) {
					return $id instanceof \DateTimeImmutable
						? $id->format('c.u')
						: (string) $id;
				},
				$id
			)
		);
	}


	/**
	 * Reads stored data
	 */
	abstract protected function readData(): array;


	/**
	 * Stores data
	 */
	abstract protected function saveData(array $data);
}
