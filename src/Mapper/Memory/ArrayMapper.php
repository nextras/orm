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
use Nextras\Orm\Mapper\BaseMapper;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\StorageReflection\CommonReflection;


/**
 * Array Mapper.
 */
abstract class ArrayMapper extends BaseMapper
{
	/** @var array */
	protected $data = [];


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


	public function createCollectionManyHasMany(IMapper $mapperTwo, PropertyMetadata $metadata, IEntity $parent)
	{
		$targetMapper = $metadata->args[2] ? $mapperTwo : $this;
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
		if ($entity->hasValue('id')) {
			$id = $entity->id;
		} else {
			// todo: lock
			$data = $this->readData();
			$id = $data ? max(array_keys($data)) + 1 : 1;
			$data[$id] = NULL;
			$this->saveData($data);
			// todo: unlock
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
		foreach ($this->data as $id => $entity) {

			$data = $entity->toArray();
			$storageProperties = $entity->getMetadata()->storageProperties;
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
		return new CommonReflection($this);
	}


	protected function initializeData()
	{
		if ($this->data) {
			return;
		}

		$repository = $this->getRepository();
		$data = $this->readData();


		foreach ($data as $row) {
			if ($row === NULL) {
				// auto increment placeholder
				continue;
			}

			$entity = $repository->hydrateEntity($row);
			$this->data[$entity->id] = $entity;
		}
	}


	/**
	 * Reads stored data
	 * @return array
	 */
	abstract protected function readData();


	/**
	 * Stores data
	 * @param  array
	 */
	abstract protected function saveData(array $data);

}
