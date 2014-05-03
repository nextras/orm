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
use Nextras\Orm\Mapper\CollectionMapper\ICollectionMapper;
use Nextras\Orm\Mapper\CollectionMapper\ICollectionMapperHasMany;
use Nextras\Orm\Mapper\CollectionMapper\ICollectionMapperHasOne;
use Nextras\Orm\StorageReflection\IStorageReflection;
use Nextras\Orm\Repository\IRepository;



interface IMapper
{

	/**
	 * Returns all entities.
	 * @return ICollection
	 */
	function findAll();


	/**
	 * Creates collection mapper.
	 * @return ICollectionMapper
	 */
	function createCollectionMapper();


	/**
	 * Creates collection wtih OneHasMany mapper.
	 * @param  IMapper
	 * @param  PropertyMetadata
	 * @param  IEntity
	 * @return ICollection
	 */
	function createCollectionOneHasMany(IMapper $mapper, PropertyMetadata $metadata, IEntity $parent);


	/**
	 * Creates collection with ManyHasMany mapper.
	 * @param  IMapper
	 * @param  PropertyMetadata
	 * @param  IEntity
	 * @return ICollection
	 */
	function createCollectionManyHasMany(IMapper $mapper, PropertyMetadata $metadata, IEntity $parent);


	/**
	 * @param  IMapper
	 * @param  PropertyMetadata
	 * @return ICollectionMapperHasMany
	 */
	function getCollectionMapperOneHasMany(IMapper $mapper, PropertyMetadata $metadata);


	/**
	 * @param  PropertyMetadata
	 * @return ICollectionMapperHasOne
	 */
	function getCollectionMapperHasOne(PropertyMetadata $metadata);


	/**
	 * @param  IMapper
	 * @param  PropertyMetadata
	 * @return ICollectionMapperHasMany
	 */
	function getCollectionMapperManyHasMany(IMapper $mapper, PropertyMetadata $metadata);


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
	 * Returns joining table name and its primary keys.
	 * @param  IMapper
	 * @return array returns array(table_name, array(primary, keys))
	 */
	function getManyHasManyParameters(IMapper $mapper);


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
