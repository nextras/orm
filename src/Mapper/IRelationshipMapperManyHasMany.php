<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper;

use Nextras\Orm\Entity\IEntity;


interface IRelationshipMapperManyHasMany extends IRelationshipMapper
{
	/**
	 * Adds entity relationshios with passed ids.
	 * @param  array $add array of ids to be connected
	 */
	public function add(IEntity $parent, array $add);


	/**
	 * Removes entity relationships with passed ids.
	 * @param  array $remove array of connected ids to be removed
	 */
	public function remove(IEntity $parent, array $remove);
}
