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
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Model\IModel;


interface IRepository
{

	/**
	 * @param  bool
	 * @return IModel
	 */
	function getModel($need = TRUE);


	/**
	 * @param  IModel
	 * @return mixed
	 * @todo: fireEvent?
	 */
	function onModelAttach(IModel $model);


	/**
	 * @return IMapper
	 */
	function getMapper();


	/**
	 * Hydrates entity.
	 * @param  array
	 * @return IEntity
	 */
	function hydrateEntity(array $data);


	/**
	 * Attaches entity to repository.
	 * @param  IEntity
	 */
	function attach(IEntity $entity);


	/**
	 * Returns available class names for entities.
	 * @return string[]
	 */
	static function getEntityClassNames();


	/**
	 * Returns entity class name.
	 * @param  array
	 * @return string
	 */
	function getEntityClassName(array $data);


	/**
	 * Returns entity by primary value.
	 * @param  mixed
	 * @return IEntity
	 */
	function getById($primaryValue);


	/**
	 * Returns entities by primary values.
	 * @param  mixed[]
	 * @return ICollection
	 */
	function findById($primaryValues);


	/**
	 * @param IEntity $entity
	 * @param bool $recursive
	 * @return mixed
	 */
	function persist(IEntity $entity, $recursive = TRUE);


	/**
	 * @param IEntity $entity
	 * @param bool $recursive
	 * @return mixed
	 */
	function persistAndFlush(IEntity $entity, $recursive = TRUE);


	/**
	 * Flushes all changes in the repository and connected repositories (by relationships).
	 */
	function flush();


	/**
	 * @param  IEntity
	 * @return IEntity
	 */
	function remove($entity);

}
