<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


/**
 * Property wrapper supporting read & write via direct property access on entity.
 */
interface IPropertyContainer extends IProperty
{
	/**
	 * Sets an injected value.
	 * This method is called when setting value directly via property access.
	 * Returns true if the setter has modified property value.
	 * @internal
	 * @param mixed $value
	 */
	public function setInjectedValue($value): bool;


	/**
	 * Returns injected value.
	 * This method is called when reading value directly via property access.
	 * @internal
	 * @return mixed
	 */
	public function &getInjectedValue();


	/**
	 * Returns true if property container has a value.
	 * This method is called when checking value direcly via property access.
	 * @internal
	 */
	public function hasInjectedValue(): bool;
}
