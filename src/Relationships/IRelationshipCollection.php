<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Countable;
use IteratorAggregate;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IProperty;


interface IRelationshipCollection extends IProperty, IteratorAggregate, Countable
{

	/**
	 * @ignore
	 * @internal
	 * @param IEntity   $parent
	 */
	public function setParent(IEntity $parent);

	/**
	 * Adds entity.
	 * @param  IEntity|scalar   $entity
	 * @return IEntity
	 */
	public function add($entity);


	/**
	 * Replaces all entities with given ones.
	 * @param  IEntity[]|scalar[]   $data
	 * @return IRelationshipCollection
	 */
	public function set(array $data);


	/**
	 * Removes entity.
	 * @param  IEntity|scalar   $entity
	 * @return IEntity
	 */
	public function remove($entity);


	/**
	 * @param  IEntity|scalar   $entity
	 * @return bool
	 */
	public function has($entity);


	/**
	 * Returns collection of all entity.
	 * @return ICollection
	 */
	public function get();


	/**
	 * @param  bool     $recursive
	 * @param  array    $queue
	 */
	public function persist($recursive = TRUE, & $queue = NULL);


	/**
	 * Returns true if colletion was loaded.
	 * @return bool
	 */
	public function isLoaded();


	/**
	 * Returns true if relationship is modified.
	 * @return bool
	 */
	public function isModified();


	/**
	 * Counts collection entities without fetching them from storage.
	 * @return int
	 */
	public function countStored();

}
