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


interface ICollectionMapperManyHasMany extends ICollectionMapperHasMany
{

	/**
	 * Adds entity relationshios with passed ids.
	 * @param  IEntity
	 * @param  array   array of ids to be connected
	 */
	function add(IEntity $parent, array $add);


	/**
	 * Removes entity relationships with passed ids.
	 * @param  IEntity
	 * @param  array   array of connected ids to be removed
	 */
	function remove(IEntity $parent, array $remove);

}
