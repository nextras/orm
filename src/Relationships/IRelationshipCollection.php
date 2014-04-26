<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Countable;
use IteratorAggregate;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Collection\ICollection;


interface IRelationshipCollection extends IteratorAggregate, Countable
{

	/**
	 * Adds entity.
	 * @param  IEntity|scalar
	 * @return IEntity
	 */
	function add($entity);


	/**
	 * Replaces all entities with given ones.
	 * @param  IEntity[]|scalar[]
	 * @return IRelationshipCollection
	 */
	function set(array $data);


	/**
	 * Removes entity.
	 * @param  IEntity|scalar
	 * @return IEntity
	 */
	function remove($entity);


	/**
	 * @param  IEntity|scalar
	 * @return bool
	 */
	function has($entity);


	/**
	 * Returns collection of all entity.
	 * @return ICollection
	 */
	function get();


	/**
	 * Returns count of entities in relationship.
	 * @return int
	 */
	function count();


	/**
	 * @param  bool Persists all associations?
	 */
	function persist($recursive = TRUE);

}
