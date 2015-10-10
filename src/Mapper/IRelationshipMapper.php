<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Traversable;


interface IRelationshipMapper
{
	/**
	 * Returns iterator.
	 * @param  IEntity      $parent
	 * @param  ICollection  $collection
	 * @return Traversable
	 */
	public function getIterator(IEntity $parent, ICollection $collection);


	/**
	 * Returns iterator's counts.
	 * @param  IEntity      $parent
	 * @param  ICollection  $collection
	 * @return int
	 */
	public function getIteratorCount(IEntity $parent, ICollection $collection);
}
