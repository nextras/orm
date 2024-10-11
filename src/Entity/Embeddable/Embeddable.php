<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Embeddable;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityAwareProperty;
use Nextras\Orm\Entity\ImmutableDataTrait;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Exception\LogicException;
use Nextras\Orm\Exception\NotSupportedException;
use function assert;
use function call_user_func;
use function count;
use function get_class;


abstract class Embeddable implements IEmbeddable
{
	use ImmutableDataTrait;


	protected IEntity|null $parentEntity = null;


	/**
	 * @param array<string, mixed>|null $data
	 */
	protected function __construct(?array $data = null)
	{
		$this->metadata = $this->createMetadata();
		if ($data !== null) {
			$this->setImmutableData($data);
		}
	}


	public function setRawValue(array $data): void
	{
		$this->metadata = $this->createMetadata();
		foreach ($this->metadata->getProperties() as $name => $propertyMetadata) {
			if ($propertyMetadata->isVirtual) continue;
			$this->data[$name] = $data[$name] ?? null;
		}
	}


	public function getRawValue(): array
	{
		$out = [];

		foreach ($this->metadata->getProperties() as $name => $propertyMetadata) {
			if ($propertyMetadata->isVirtual) continue;
			if (!isset($this->validated[$name])) {
				$this->initProperty($propertyMetadata, $name);
			}

			if ($propertyMetadata->wrapper === null) {
				$out[$name] = $this->data[$name];
			} else {
				$wrapper = $this->data[$name];
				assert($wrapper instanceof IProperty);
				$out[$name] = $wrapper->getRawValue();
			}
		}

		return $out;
	}


	public function onAttach(IEntity $entity): void
	{
		$this->parentEntity = $entity;
	}




	/**
	 * @return mixed
	 */
	public function &__get(string $name)
	{
		return $this->getValue($name);
	}


	public function __isset(string $name): bool
	{
		if (!$this->metadata->hasProperty($name)) {
			return false;
		}
		return $this->hasValue($name);
	}


	/**
	 * @param mixed $value
	 * @throws NotSupportedException
	 */
	public function __set(string $name, $value): void
	{
		throw new NotSupportedException("Embeddable object is immutable.");
	}


	/**
	 * @throws NotSupportedException
	 */
	public function __unset(string $name)
	{
		throw new NotSupportedException("Embeddable object is immutable.");
	}


	protected function initProperty(PropertyMetadata $metadata, string $name, bool $initValue = true): void
	{
		$this->validated[$name] = true;

		if ($metadata->wrapper !== null) {
			$wrapper = $this->createPropertyWrapper($metadata);
			if ($initValue) {
				$wrapper->setRawValue($this->data[$metadata->name] ?? null);
			}
			$this->data[$name] = $wrapper;
			return;
		}

		// embeddable does not support property default value by design
		$this->data[$name] = $this->data[$name] ?? null;
	}


	private function createPropertyWrapper(PropertyMetadata $metadata): IProperty
	{
		$class = $metadata->wrapper;
		$wrapper = new $class($metadata);
		assert($wrapper instanceof IProperty);

		if ($wrapper instanceof IEntityAwareProperty) {
			if ($this->parentEntity === null) {
				throw new InvalidStateException("Embeddable cannot contain a property having IEntityAwareProperty wrapper because embeddable is instanced before setting/attaching to its entity.");
			} else {
				$wrapper->onEntityAttach($this->parentEntity);
			}
		}

		return $wrapper;
	}


	/**
	 * @param array<string, mixed> $data
	 */
	private function setImmutableData(array $data): void
	{
		if (count($data) !== count($this->metadata->getProperties())) {
			$n = count($data);
			$total = count($this->metadata->getProperties());
			$class = get_class($this);
			throw new InvalidArgumentException("Only $n of $total values were set. Construct $class embeddable with all its properties. ");
		}

		foreach ($data as $name => $value) {
			$metadata = $this->metadata->getProperty($name);
			if (!isset($this->validated[$name])) {
				$this->initProperty($metadata, $name, false);
			}

			$property = $this->data[$name];
			if ($property instanceof IPropertyContainer) {
				$property->setInjectedValue($value);
				continue;
			} elseif ($property instanceof IProperty) {
				$class = get_class($this);
				throw new LogicException("You cannot set property wrapper's value in $class::\$$name directly.");
			}

			if ($metadata->hasSetter !== null) {
				$cb = [$this, $metadata->hasSetter];
				assert(is_callable($cb));
				$value = call_user_func($cb, $value, $metadata);
			}

			$this->validate($metadata, $name, $value);
			$this->data[$name] = $value;
		}
	}
}
