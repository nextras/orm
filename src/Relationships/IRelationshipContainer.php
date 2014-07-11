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


interface IRelationshipContainer extends IPropertyContainer
{

	/**
	 * @ignore
	 * @internal
	 * @param  IEntity  $parent
	 */
	public function setParent(IEntity $parent);


	/**
	 * @param  string   $collectionName
	 * @return IEntity
	 */
	public function getEntity($collectionName = NULL);


	/**
	 * @return mixed
	 */
	public function getPrimaryValue();

}
