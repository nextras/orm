<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper;

use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\StorageReflection\IStorageReflection;
use Nextras\Orm\Repository\IRepository;
use stdClass;


interface IMapper
{

	/**
	 * Returns all entities.
	 * @return ICollection
	 */
	public function findAll();


	/**
	 * Returns cache object for collections.
	 * @return stdClass
	 */
	public function getCollectionCache();


	/**
	 * Creates collection with HasOne mapper.
	 * @param  PropertyMetadata $metadata
	 * @param  IEntity          $parent
	 * @return ICollection
	 */
	public function createCollectionHasOne(PropertyMetadata $metadata, IEntity $parent);


	/**
	 * Creates collection with OneHasOneDirected mapper.
	 * @param  PropertyMetadata $metadata
	 * @param  IEntity          $parent
	 * @return ICollection
	 */
	public function createCollectionOneHasOneDirected(PropertyMetadata $metadata, IEntity $parent);


	/**
	 * Creates collection with ManyHasMany mapper.
	 * @param  IMapper          $mapper
	 * @param  PropertyMetadata $metadata
	 * @param  IEntity          $parent
	 * @return ICollection
	 */
	public function createCollectionManyHasMany(IMapper $mapper, PropertyMetadata $metadata, IEntity $parent);


	/**
	 * Creates collection with OneHasMany mapper.
	 * @param  PropertyMetadata $metadata
	 * @param  IEntity          $parent
	 * @return ICollection
	 */
	public function createCollectionOneHasMany(PropertyMetadata $metadata, IEntity $parent);


	/**
	 * @param  IRepository  $repository
	 */
	public function setRepository(IRepository $repository);


	/**
	 * @return IRepository
	 */
	public function getRepository();


	/**
	 * @return string
	 */
	public function getTableName();


	/**
	 * @return IStorageReflection
	 */
	public function getStorageReflection();


	/**
	 * @see    IRepository::persist()
	 * @param  IEntity  $entity
	 * @return IEntity
	 */
	public function persist(IEntity $entity);


	/**
	 * @see    IRepository::remove()
	 * @param  IEntity  $entity
	 * @return bool
	 */
	public function remove(IEntity $entity);


	/**
	 * @see    IRepository::flush()
	 * @return void
	 */
	public function flush();


	/**
	 * @see    IRepository::roolback()
	 * @return void
	 */
	public function rollback();

}
