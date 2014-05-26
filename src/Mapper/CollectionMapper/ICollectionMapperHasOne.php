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


interface ICollectionMapperHasOne
{

	/**
	 * Returns dependent entity in the relationsip with parent.
	 * @param  IEntity
	 * @return IEntity
	 */
	function getEntity(IEntity $parent);

}
