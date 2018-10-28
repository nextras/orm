<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\PropertyMetadata;


abstract class ImmutableValuePropertyContainer implements IPropertyContainer
{
	/** @var null|mixed */
	protected $value;

	/** @var PropertyMetadata */
	private $propertyMetadata;


	public function __construct(PropertyMetadata $propertyMetadata)
	{
		$this->propertyMetadata = $propertyMetadata;
	}


	public function loadValue(IEntity $entity, array $values): void
	{
		$this->setRawValue($values[$this->propertyMetadata->name]);
	}


	public function saveValue(IEntity $entity, array $values): array
	{
		$values[$this->propertyMetadata->name] = $this->getRawValue();
		return $values;
	}


	public function setRawValue($value)
	{
		$this->value = $value === null ? null : $this->convertFromRawValue($value);
	}


	public function getRawValue()
	{
		return $this->value === null ? null : $this->convertToRawValue($this->value);
	}


	public function &getInjectedValue(IEntity $entity)
	{
		return $this->value;
	}


	public function hasInjectedValue(IEntity $entity): bool
	{
		return $this->value !== null;
	}


	public function setInjectedValue(IEntity $entity, $value)
	{
		if ($this->isModified($this->value, $value)) {
			$entity->setAsModified($this->propertyMetadata->name);
		}
		$this->value = $value;
	}


	protected function isModified($oldValue, $newValue): bool
	{
		return $oldValue !== $newValue;
	}


	/**
	 * @internal
	 * @param  mixed $value
	 * @return mixed
	 */
	abstract public function convertFromRawValue($value);
}
