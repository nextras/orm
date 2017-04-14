<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;


interface IEntityPreloadContainer
{
	/**
	 * Returns array of $property values for preloading.
	 */
	public function getPreloadValues(string $property): array;
}
