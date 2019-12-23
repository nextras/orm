<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Embeddable;

use Nextras\Orm\Entity\IEntity;


interface IEmbeddable
{
	/**
	 * Returns true if property has a not null value.
	 */
	public function hasValue(string $name): bool;


	/**
	 * Returns value.
	 * @return mixed
	 */
	public function &getValue(string $name);


	/**
	 * Loads raw value from passed array.
	 * @internal
	 */
	public function setRawValue(array $data);


	/**
	 * Stores raw value and returns it as array.
	 * @internal
	 */
	public function getRawValue(): array;


	/**
	 * Attaches entity to embeddable object.
	 * This is called after injecting embeddable into property wrapper.
	 * @internal
	 */
	public function onAttach(IEntity $entity);
}
