<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Embeddable;


interface IEmbeddable
{
	/**
	 * Returns value.
	 * @return mixed
	 */
	public function &getValue(string $name);


	/**
	 * Returns true if property has a value (not null).
	 */
	public function hasValue(string $name): bool;


	/**
	 * Load event.
	 * @return void
	 */
	public function onLoad(array $data);
}
