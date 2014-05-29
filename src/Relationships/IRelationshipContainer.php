<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\IEntity;


interface IRelationshipContainer
{

	/**
	 * @ignore
	 * @internal
	 */
	function setParent(IEntity $parent);

}
