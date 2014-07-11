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
	 * @param  bool $recursive Persists all associations?
	 */
	public function persist($recursive = TRUE);


	/**
	 * Returns true if colletion was loaded.
	 * @return bool
	 */
	public function isLoaded();

}
