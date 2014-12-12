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
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\IPropertyHasRawValue;


interface IRelationshipContainer extends IPropertyContainer, IPropertyHasRawValue
{

	/**
	 * @ignore
	 * @internal
	 * @param  IEntity  $parent
	 */
	public function setParent(IEntity $parent);


	/**
	 * @return IEntity
	 */
	public function getEntity();


	/**
	 * @return mixed
	 */
	public function getPrimaryValue();


	/**
	 * Returns true if container was loaded.
	 * @return bool
	 */
	public function isLoaded();

}
