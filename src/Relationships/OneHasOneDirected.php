<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;


class OneHasOneDirected extends OneHasOne
{

	protected function modify()
	{
		$this->isModified = TRUE;
		if ($this->metadata->relationship->isMain) {
			$this->parent->setAsModified($this->metadata->name);
		}
	}


	protected function createCollection()
	{
		return $this->getTargetRepository()->getMapper()->createCollectionOneHasOneDirected($this->metadata, $this->parent);
	}

}
