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
	function findAll();


	/**
	 * Returns cache object for collections.
	 * @return stdClass
	 */
	function getCollectionCache();


	/**
	 * Creates collection with HasOne mapper.
	 * @param  PropertyMetadata
	 * @param  IEntity
	 * @return ICollection
	 */
	function createCollectionHasOne(PropertyMetadata $metadata, IEntity $parent);


	/**
	 * Creates collection with OneHasOneDirected mapper.
	 * @param  PropertyMetadata
	 * @param  IEntity
	 * @return ICollection
	 */
	function createCollectionOneHasOneDirected(PropertyMetadata $metadata, IEntity $parent);


	/**
	 * Creates collection with ManyHasMany mapper.
	 * @param  IMapper
	 * @param  PropertyMetadata
	 * @param  IEntity
	 * @return ICollection
	 */
	function createCollectionManyHasMany(IMapper $mapper, PropertyMetadata $metadata, IEntity $parent);


	/**
	 * Creates collection with OneHasMany mapper.
	 * @param  PropertyMetadata
	 * @param  IEntity
	 * @return ICollection
	 */
	function createCollectionOneHasMany(PropertyMetadata $metadata, IEntity $parent);


	/**
	 * @param  IRepository $repository
	 */
	function setRepository(IRepository $repository);


	/**
	 * @return IRepository
	 */
	function getRepository();


	/**
	 * @return string
	 */
	function getTableName();


	/**
	 * @return IStorageReflection
	 */
	function getStorageReflection();


	/**
	 * @see    IRepository::persist()
	 * @param  IEntity
	 * @return IEntity
	 */
	function persist(IEntity $entity);


	/**
	 * @see    IRepository::remove()
	 * @param  IEntity
	 * @return bool
	 */
	function remove(IEntity $entity);


	/**
	 * @see    IRepository::flush()
	 * @return void
	 */
	function flush();


	/**
	 * @see    IRepository::roolback()
	 * @return void
	 */
	function rollback();

}
