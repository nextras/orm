<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\NotImplementedException;


class JsonProxy implements IPropertyContainer
{

	/** @var callable */
	private $onModified;

	/** @var NULL|IModifiable */
	private $property;

	/** @var PropertyMetadata */
	private $propertyMetadata;


	public function __construct(IEntity $entity, PropertyMetadata $propertyMetadata)
	{
		$this->onModified = function() use ($entity, $propertyMetadata) {
			$entity->setAsModified($propertyMetadata->name);
		};
		$this->propertyMetadata = $propertyMetadata;
	}


	/**
	 * Sets raw value.
	 *
	 * @param  mixed $value
	 */
	public function setRawValue($value)
	{
		if ($value === NULL) {
			$this->property = NULL;
			return;
		}

		// example
		if (isset($this->propertyMetadata->types[LocationStruct::class])) {
			$data = json_decode($value);
			$this->property = new LocationStruct($data->street, $data->city);
			return;
		}

		throw new NotImplementedException;
	}


	/**
	 * Returns raw value.
	 * Raw value is normalized value which is suitable unique identification and storing.
	 *
	 * @return mixed
	 */
	public function getRawValue()
	{
		if ($this->property === NULL) {
			return NULL;
		}

		// example
		if (isset($this->propertyMetadata->types[LocationStruct::class])) {
			return json_encode([
				'street' => $this->property->getStreet(),
				'city' => $this->property->getCity(),
			]);
		}

		throw new NotImplementedException;
	}


	/**
	 * Sets value.
	 *
	 * @internal
	 * @param mixed $value
	 */
	public function setInjectedValue($value)
	{
		$this->property = $value;
	}


	/**
	 * Returns injected value.
	 *
	 * @internal
	 * @return mixed
	 */
	public function & getInjectedValue()
	{
		return $this->property;
	}


	/**
	 * Returns wheter property container has a value.
	 *
	 * @return bool
	 */
	public function hasInjectedValue()
	{
		return $this->property !== NULL;
	}

}
