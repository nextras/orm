<?php

/**
 * This file is part of the Nextras\ORM library.
 * This file was inspired by PetrP's ORM library https://github.com/PetrP/Orm/.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Repository;

use Nextras\Orm\Entity\Collection\ICollection;
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
	public function getModel($need = TRUE);


	/**
	 * @param  IModel   $model
	 * @return mixed
	 * @todo: fireEvent?
	 */
	public function onModelAttach(IModel $model);


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
	 * Returns available class names for entities.
	 * @return string[]
	 */
	static function getEntityClassNames();


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
	 * Returns entity by primary value.
	 * @param  mixed    $primaryValue
	 * @return IEntity
	 */
	public function getById($primaryValue);


	/**
	 * Returns entities by primary values.
	 * @param  mixed[]  $primaryValues
	 * @return ICollection
	 */
	public function findById($primaryValues);


	/**
	 * @param  IEntity   $entity
	 * @param  bool      $recursive
	 * @return mixed
	 */
	public function persist(IEntity $entity, $recursive = TRUE);


	/**
	 * @param IEntity   $entity
	 * @param  bool     $recursive
	 * @return mixed
	 */
	public function persistAndFlush(IEntity $entity, $recursive = TRUE);


	/**
	 * Flushes all changes in the repository and connected repositories (by relationships).
	 */
	public function flush();


	/**
	 * @param  IEntity  $entity
	 * @return IEntity
	 */
	public function remove($entity);

}
