<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Memory;

use DateTime;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Collection\ArrayCollection;
use Nextras\Orm\Entity\IPropertyInjection;
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
		$this->initializeData();
		return new ArrayCollection($this->data);
	}


	public function createCollectionHasOne(PropertyMetadata $metadata, IEntity $parent)
	{
		$this->initializeData();
		return new ArrayCollection($this->data, new RelationshipMapperHasOne($metadata), $parent);
	}


	public function createCollectionOneHasOneDirected(PropertyMetadata $metadata, IEntity $parent)
	{
		$this->initializeData();
		if ($metadata->relationshipIsMain) {
			return new ArrayCollection($this->data, new RelationshipMapperHasOne($metadata), $parent);
		} else {
			return new ArrayCollection($this->data, new RelationshipMapperOneHasOneDirected($this, $metadata), $parent);
		}
	}


	public function createCollectionManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata, IEntity $parent)
	{
		$targetMapper = $metadata->relationshipIsMain ? $mapperTwo : $this;
		$targetMapper->initializeData();
		return new ArrayCollection($targetMapper->data, new RelationshipMapperManyHasMany($metadata), $parent);
	}


	public function createCollectionOneHasMany(PropertyMetadata $metadata, IEntity $parent)
	{
		$this->initializeData();
		return new ArrayCollection($this->data, new RelationshipMapperOneHasMany($this, $metadata), $parent);
	}


	public function persist(IEntity $entity)
	{
		$this->initializeData();
		if ($entity->isPersisted()) {
			$id = $entity->id;
		} else {
			$this->lock();
			try {
				$data = $this->readData();
				$id = $data ? max(array_keys($data)) + 1 : 1;
				$data[$id] = NULL;
				$this->saveData($data);
			} catch (\Exception $e) { // finally workaround
			}
			$this->unlock();
			if (isset($e)) {
				throw $e;
			}
		}

		$this->data[$id] = $entity;
		return $id;
	}


	public function remove(IEntity $entity)
	{
		$this->initializeData();
		unset($this->data[$entity->id]);
	}


	public function flush()
	{
		$storageData = $this->readData();
		foreach ((array) $this->data as $id => $entity) {

			$data = $entity->toArray();
			$storageProperties = $entity->getMetadata()->getStorageProperties();
			foreach ($data as $key => $value) {
				if (!in_array($key, $storageProperties, TRUE)) {
					unset($data[$key]);
				}
				if ($value instanceof IPropertyInjection) {
					$data[$key] = $value->getStorableValue();
				} elseif ($value instanceof IEntity)  {
					$data[$key] = $value->id;
				} elseif ($value instanceof DateTime) {
					$data[$key] = $value->format('c');
				}
			}

			$data = $this->getStorageReflection()->convertEntityToStorage($data);
			$storageData[$id] = $data;
		}

		$this->saveData($storageData);
	}


	public function rollback()
	{
		$this->data = NULL;
	}


	protected function createStorageReflection()
	{
		return new CommonReflection($this, $this->getTableName());
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
