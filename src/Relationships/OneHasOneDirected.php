<?php

/**
 * This file is part of the Nextras\Orm library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;


class OneHasOneDirected extends OneHasOne
{

	protected function createCollection()
	{
		return $this->getTargetRepository()->getMapper()->createCollectionOneHasOneDirected($this->propertyMeta, $this->parent);
	}

}
