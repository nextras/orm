<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\CollectionMapper;

use Nextras\Orm\Entity\IEntity;
use Traversable;


interface ICollectionMapperHasMany
{

	/**
	 * Returns iterator.
	 * @param  IEntity
	 * @param  ICollectionMapper|NULL
	 * @return Traversable
	 */
	function getIterator(IEntity $parent, ICollectionMapper $collectionMapper = NULL);


	/**
	 * Return iterator's counts.
	 * @param  IEntity
	 * @param  ICollectionMapper|NULL
	 * @return int
	 */
	function getIteratorCount(IEntity $parent, ICollectionMapper $collectionMapper = NULL);

}
