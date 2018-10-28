<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Collection\IEntityPreloadContainer;


interface IEntityHasPreloadContainer
{
	/**
	 * Sets the collection of entites for the loading relations at once.
	 */
	public function setPreloadContainer(?IEntityPreloadContainer $overIterator);


	public function getPreloadContainer(): ?IEntityPreloadContainer;
}
