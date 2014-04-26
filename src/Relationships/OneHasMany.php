<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\NotImplementedException;


class OneHasMany extends HasMany implements IRelationshipCollection
{

	public function persist($recursive = TRUE)
	{
		throw new NotImplementedException();
	}


	protected function createCollection()
	{
		return $this->parent->getRepository()->getMapper()->createCollectionOneHasMany($this->metadata, $this->parent);
	}


	protected function getMapper()
	{
		return $this->parent->getRepository()->getMapper()->getCollectionMapperOneHasMany($this->metadata);
	}

}
