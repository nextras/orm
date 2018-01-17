<?php declare(strict_types = 1);

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
	 */
	public function setParent(IEntity $parent);


	/**
	 * @return IEntity|null|false
	 */
	public function getEntity();


	/**
	 * Returns true if container was loaded.
	 */
	public function isLoaded(): bool;


	/**
	 * Returns true if relationship is modified.
	 */
	public function isModified(): bool;
}
