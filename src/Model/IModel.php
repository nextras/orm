<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Model;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Repository\IRepository;


interface IModel
{
	/** @const use as an argument when needed */
	const I_KNOW_WHAT_I_AM_DOING = 'i_know_what_i_am_doing';


	/**
	 * Returns true if repository with name is attached to model.
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
	 * Returns true if repository class is attached to model.
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


	/**
	 * Persist the entity with cascade.
	 * @param  IEntity $entity
	 * @return IEntity
	 */
	public function persist(IEntity $entity, $withCascade = true);


	/**
	 * @param  IEntity $entity
	 * @param  bool $withCascade
	 * @return mixed
	 */
	public function remove(IEntity $entity, $withCascade = true);


	/**
	 * Flushes all persisted changes in repositories.
	 * @return void
	 */
	public function flush();


	/**
	 * Persist the entity with cascade and flushes the model.
	 * @param  IEntity $entity
	 * @return IEntity
	 */
	public function persistAndFlush(IEntity $entity);


	/**
	 * USE ONLY IF YOU ARE SURE YOU KNOW WHAT ARE YOU DOING.
	 * Clears repository identity map and other possible caches.
	 * Make sure that all references to already used entites are released,
	 * this makes possible to free the memory for garbage collector.
	 * Orm will not allow you to work with these entities anymore.
	 * @dangerous
	 * @internal
	 * @ignore
	 */
	public function clearIdentityMapAndCaches($areYouSure);
}
