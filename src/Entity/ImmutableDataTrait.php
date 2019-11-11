<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;


trait ImmutableDataTrait
{
	/** @var EntityMetadata */
	protected $metadata;

	/** @var array */
	private $data = [];

	/** @var array */
	private $validated = [];


	public function &getValue(string $name)
	{
		$property = $this->metadata->getProperty($name);
		return $this->internalGetValue($property, $name);
	}


	public function hasValue(string $name): bool
	{
		$property = $this->metadata->getProperty($name);
		return $this->internalHasValue($property, $name);
	}


	protected function createMetadata(): EntityMetadata
	{
		return MetadataStorage::get(get_class($this));
	}


	private function &internalGetValue(PropertyMetadata $metadata, string $name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->getInjectedValue();
		}

		if ($metadata->hasGetter) {
			/** @var callable $cb */
			$cb = [$this, $metadata->hasGetter];
			$value = call_user_func(
				$cb,
				$metadata->isVirtual ? null : $this->data[$name],
				$metadata
			);
		} else {
			$value = $this->data[$name];
		}
		if ($value === null && !$metadata->isNullable) {
			$class = get_class($this);
			throw new InvalidStateException("Property {$class}::\${$name} is not set.");
		}
		return $value;
	}


	private function internalHasValue(PropertyMetadata $metadata, string $name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->hasInjectedValue();

		} elseif ($metadata->hasGetter) {
			/** @var callable $cb */
			$cb = [$this, $metadata->hasGetter];
			$value = call_user_func(
				$cb,
				$metadata->isVirtual ? null : $this->data[$name],
				$metadata
			);
			return isset($value);

		} else {
			return isset($this->data[$name]);
		}
	}


	/**
	 * Validates the value.
	 * @param mixed $value
	 * @throws InvalidArgumentException
	 */
	protected function validate(PropertyMetadata $metadata, string $name, & $value)
	{
		if (!$metadata->isValid($value)) {
			$class = get_class($this);
			throw new InvalidArgumentException("Value for {$class}::\${$name} property is invalid.");
		}
	}


	abstract protected function initProperty(PropertyMetadata $metadata, string $name, bool $initValue = true);
}
