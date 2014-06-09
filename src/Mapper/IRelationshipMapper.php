<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper;

use Nextras\Orm\Entity\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Traversable;


interface IRelationshipMapper
{

	/**
	 * Returns iterator.
	 * @param  IEntity
	 * @param  ICollection
	 * @return Traversable
	 */
	function getIterator(IEntity $parent, ICollection $collection);


	/**
	 * Returns iterator's counts.
	 * @param  IEntity
	 * @param  ICollection
	 * @return int
	 */
	function getIteratorCount(IEntity $parent, ICollection $collection);

}
