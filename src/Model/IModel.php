<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Model;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IRepository;


interface IModel
{

	/**
	 * Returns TRUE if repository with name is attached to model.
	 * @param  string   $name
	 * @return bool
	 */
	public function hasRepositoryByName($name);


	/**
	 * Returns repository by repository name.
	 * @param  string   $name
	 * @return IRepository
	 */
	public function getRepositoryByName($name);


	/**
	 * Returns TRUE if repository class is attached to model.
	 * @param  string   $className
	 * @return bool
	 */
	public function hasRepository($className);


	/**
	 * Returns repository by repository class.
	 * @param  string   $className
	 * @return IRepository
	 */
	public function getRepository($className);


	/**
	 * Returns repository associated for entity type.
	 * @param  IEntity|string   $entity
	 * @return IRepository
	 */
	public function getRepositoryForEntity($entity);


	/**
	 * Returns entity metadata storage.
	 * @return MetadataStorage
	 */
	public function getMetadataStorage();

}
