<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity;


use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;


trait ImmutableDataTrait
{
	/** @var EntityMetadata */
	protected EntityMetadata $metadata;

	/** @var array<string, mixed> */
	private array $data = [];

	/** @var array<string, bool> */
	private array $validated = [];


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


	/**
	 * @return mixed
	 */
	private function &internalGetValue(PropertyMetadata $metadata, string $name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->getInjectedValue();
		}

		if ($metadata->hasGetter !== null) {
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


	private function internalHasValue(PropertyMetadata $metadata, string $name): bool
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name, false);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->hasInjectedValue();

		} elseif ($metadata->hasGetter !== null) {
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
	protected function validate(PropertyMetadata $metadata, string $name, &$value): void
	{
		if (!$metadata->isValid($value)) {
			$class = get_class($this);
			throw new InvalidArgumentException("Value for {$class}::\${$name} property is invalid.");
		}
	}


	abstract protected function initProperty(PropertyMetadata $metadata, string $name, bool $initValue = true): void;
}
