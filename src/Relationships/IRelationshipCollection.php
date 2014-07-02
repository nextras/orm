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
use Nextras\Orm\Entity\IPropertyInjection;


interface IRelationshipCollection extends IPropertyInjection, IteratorAggregate, Countable
{

	/**
	 * @ignore
	 * @internal
	 */
	function setParent(IEntity $parent);

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
	 * @param  bool Persists all associations?
	 */
	function persist($recursive = TRUE);


	/**
	 * Returns true if colletion was loaded.
	 * @return bool
	 */
	function isLoaded();

}
