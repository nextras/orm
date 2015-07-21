<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Memory;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


class RelationshipMapperOneHasOneDirected extends RelationshipMapperOneHasMany
{

	public function getIterator(IEntity $parent, ICollection $collection)
	{
		return [parent::getIterator($parent, $collection)->current()];
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		throw new NotSupportedException();
	}

}
