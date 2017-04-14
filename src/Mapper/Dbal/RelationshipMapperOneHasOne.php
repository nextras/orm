<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use ArrayIterator;
use Iterator;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\NotSupportedException;


class RelationshipMapperOneHasOne extends RelationshipMapperOneHasMany
{
	public function getIterator(IEntity $parent, ICollection $collection): Iterator
	{
		return new ArrayIterator([
			parent::getIterator($parent, $collection)->current(),
		]);
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection): int
	{
		throw new NotSupportedException();
	}
}
