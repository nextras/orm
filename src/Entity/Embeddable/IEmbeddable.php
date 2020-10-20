<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Embeddable;


use Nextras\Orm\Entity\IEntity;


/**
 * @experimental This interface API is experimental and is subjected to change. It is ok to use its implementation.
 */
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
	 * @param array<string, mixed> $data
	 * @internal
	 */
	public function setRawValue(array $data): void;


	/**
	 * Stores raw value and returns it as array.
	 * @return array<string, mixed>
	 * @internal
	 */
	public function getRawValue(): array;


	/**
	 * Attaches entity to embeddable object.
	 * This is called after injecting embeddable into property wrapper.
	 * @internal
	 */
	public function onAttach(IEntity $entity): void;
}
