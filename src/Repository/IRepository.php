<?php

/**
 * This file is part of the Nextras\Orm library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Repository;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;


interface IRepository
{
	/**
	 * @param  bool $need
	 * @return IModel
	 */
	public function getModel($need = true);


	/**
	 * @param  IModel   $model
	 */
	public function setModel(IModel $model);


	/**
	 * @return IMapper
	 */
	public function getMapper();


	/**
	 * Hydrates entity.
	 * @param  array    $data
	 * @return IEntity
	 */
	public function hydrateEntity(array $data);


	/**
	 * Attaches entity to repository.
	 * @param  IEntity  $entity
	 */
	public function attach(IEntity $entity);


	/**
	 * Detaches entity from repository.
	 * @param  IEntity  $entity
	 */
	public function detach(IEntity $entity);


	/**
	 * Returns possible entity class names for current repository.
	 * @return string[]
	 */
	public static function getEntityClassNames();


	/**
	 * Returns entity metadata.
	 * @return EntityMetadata
	 */
	public function getEntityMetadata();


	/**
	 * Returns entity class name.
	 * @param  array    $data
	 * @return string
	 */
	public function getEntityClassName(array $data);


	/**
	 * Returns IEntity filtered by conditions
	 * @param  array $where
	 * @return IEntity|null
	 */
	public function getBy(array $conds);


	/**
	 * Returns entity by primary value.
	 * @param  mixed    $primaryValue
	 * @return IEntity
	 */
	public function getById($primaryValue);


	/**
	 * Returns entity collection with all entities.
	 * @return ICollection
	 */
	public function findAll();


	/**
	 * Returns entity collection filtered by conditions.
	 * @param  array $where
	 * @return ICollection
	 */
	public function findBy(array $where);


	/**
	 * Returns entities by primary values.
	 * @param  mixed[]  $primaryValues
	 * @return ICollection
	 */
	public function findById($primaryValues);


	/**
	 * @param  IEntity  $entity
	 * @param  bool     $withCascade
	 * @return mixed
	 */
	public function persist(IEntity $entity, $withCascade = true);


	/**
	 * @param  IEntity  $entity
	 * @param  bool     $withCascade
	 * @return mixed
	 */
	public function persistAndFlush(IEntity $entity, $withCascade = true);


	/**
	 * @param  IEntity|mixed    $entity
	 * @param  bool             $withCascade
	 * @return IEntity
	 */
	public function remove($entity, $withCascade = true);


	/**
	 * @param  IEntity|mixed    $entity
	 * @param  bool             $withCascade
	 * @return IEntity
	 */
	public function removeAndFlush($entity, $withCascade = true);


	/**
	 * Flushes all persisted changes in all repositories.
	 */
	public function flush();


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 */
	public function doPersist(IEntity $entity);


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 */
	public function doRemove(IEntity $entity);


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 * The first key contains all flushed persisted entities.
	 * The second key contains all flushed removed entities.
	 * @return [IEntity[], IEntity[]]
	 */
	public function doFlush();


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * @internal
	 * @ignore
	 */
	public function doClearIdentityMap($areYouSure);


	/**
	 * DO NOT CALL THIS METHOD DIRECTLY.
	 * Fires the event on the entity.
	 * @internal
	 * @ignore
	 * @param  IEntity $entity
	 * @param  string $event
	 * @return void
	 */
	public function doFireEvent(IEntity $entity, $event);
}
