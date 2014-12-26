<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Nette;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\NotSupportedException;


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
