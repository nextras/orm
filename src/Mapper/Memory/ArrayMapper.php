<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Memory;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Collection\ArrayCollection;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\IOException;
use Nextras\Orm\LogicException;
use Nextras\Orm\Mapper\BaseMapper;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\StorageReflection\CommonReflection;


/**
 * Array Mapper.
 */
abstract class ArrayMapper extends BaseMapper
{
	/** @var array */
	protected $data;

	/** @var resource */
	static protected $lock;


	public function findAll()
	{
		return new ArrayCollection($this->getData());
	}


	public function createCollectionHasOne(PropertyMetadata $metadata, IEntity $parent)
	{
		return new ArrayCollection($this->getData(), new RelationshipMapperHasOne($metadata), $parent);
	}


	public function createCollectionOneHasOneDirected(PropertyMetadata $metadata, IEntity $parent)
	{
		if ($metadata->relationshipIsMain) {
			return new ArrayCollection($this->getData(), new RelationshipMapperHasOne($metadata), $parent);
		} else {
			return new ArrayCollection($this->getData(), new RelationshipMapperOneHasOneDirected($this, $metadata), $parent);
		}
	}


	public function createCollectionManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata, IEntity $parent)
	{
		$targetMapper = $metadata->relationshipIsMain ? $mapperTwo : $this;
		return new ArrayCollection($targetMapper->getData(), new RelationshipMapperManyHasMany($metadata), $parent);
	}


	public function createCollectionOneHasMany(PropertyMetadata $metadata, IEntity $parent)
	{
		return new ArrayCollection($this->getData(), new RelationshipMapperOneHasMany($this, $metadata), $parent);
	}


	public function persist(IEntity $entity)
	{
		$this->initializeData();
		if ($entity->isPersisted()) {
			$id = $entity->getValue('id');
		} else {
			$this->lock();
			try {
				$data = $this->readData();
				$id = $entity->getValue('id');
				if ($id === NULL) {
					$id = $data ? max(array_keys($data)) + 1 : 1;
				}
				$data[implode(',', (array) $id)] = NULL;
				$this->saveData($data);
			} catch (\Exception $e) { // finally workaround
			}
			$this->unlock();
			if (isset($e)) {
				throw $e;
			}
		}

		$this->data[implode(',', (array) $id)] = $entity;
		return $id;
	}


	public function remove(IEntity $entity)
	{
		$this->initializeData();
		$this->data[implode(',', (array) $entity->getPersistedId())] = NULL;
	}


	public function flush()
	{
		parent::flush();
		$storageData = $this->readData();
		foreach ((array) $this->data as $id => $entity) {
			/** @var IEntity $entity */
			if ($entity === NULL) {
				$storageData[implode(',', (array) $id)] = NULL;
				continue;
			}

			$data = $this->entityToArray($entity);
			$data = $this->getStorageReflection()->convertEntityToStorage($data);
			$storageData[implode(',', (array) $id)] = $data;
		}

		$this->saveData($storageData);
	}


	public function rollback()
	{
		$this->data = NULL;
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
		if ($this->data !== NULL) {
			return;
		}

		$repository = $this->getRepository();
		$data = $this->readData();

		$this->data = [];
		foreach ($data as $row) {
			if ($row === NULL) {
				// auto increment placeholder
				continue;
			}

			$entity = $repository->hydrateEntity($row);
			$this->data[$entity->id] = $entity;
		}
	}


	protected function getData()
	{
		$this->initializeData();
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
		self::$lock = NULL;
	}


	protected function entityToArray(IEntity $entity)
	{
		$return = [];
		$metadata = $entity->getMetadata();

		foreach ($metadata->getProperties() as $name => $metadataProperty) {
			if ($metadataProperty->isVirtual) {
				continue;
			}

			$property = $entity->getProperty($name);
			if ($property instanceof IRelationshipCollection && $metadataProperty->relationshipType === PropertyMetadata::RELATIONSHIP_ONE_HAS_MANY) {
				continue;
			}

			if ($property instanceof IPropertyStorableConverter) {
				$value = $property->getMemoryStorableValue();

			} else {
				$value = $entity->getValue($name);
			}

			$return[$name] = $value;
		}

		return $return;
	}


	/**
	 * Reads stored data
	 * @return array
	 */
	abstract protected function readData();


	/**
	 * Stores data
	 * @param  array    $data
	 */
	abstract protected function saveData(array $data);

}
