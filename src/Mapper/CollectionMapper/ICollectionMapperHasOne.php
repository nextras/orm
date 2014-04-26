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
	 * Returns iterator.
	 * @param  IEntity
	 * @return IEntity
	 */
	function getItem(IEntity $parent);

}
