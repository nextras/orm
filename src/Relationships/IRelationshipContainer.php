<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IPropertyContainer;


interface IRelationshipContainer extends IPropertyContainer
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
	 * Returns true if container was loaded.
	 * @return bool
	 */
	public function isLoaded();


	/**
	 * Returns true if relationship is modified.
	 * @return bool
	 */
	public function isModified();
}
