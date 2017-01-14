<?php declare(strict_types=1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Memory;

use ArrayIterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;


class RelationshipMapperOneHasOne extends RelationshipMapperOneHasMany
{
	public function getIterator(IEntity $parent, ICollection $collection)
	{
		return new ArrayIterator([
			parent::getIterator($parent, $collection)->current(),
		]);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		throw new NotSupportedException();
	}
}
