<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Embeddable;


use Nette\SmartObject;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityAwareProperty;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\NullValueException;


class EmbeddableContainer implements IPropertyContainer, IEntityAwareProperty
{
	use SmartObject;

	/** @var PropertyMetadata */
	private $metadata;

	/** @var IEntity|null */
	private $entity;

	/** @var IEmbeddable|null */
	private $value;

	/** @var PropertyMetadata[] */
	private $propertiesMetadata = [];

	/** @var string */
	private $instanceType;


	public function __construct(PropertyMetadata $propertyMetadata)
	{
		\assert($propertyMetadata->args !== null);
		$this->metadata = $propertyMetadata;
		$this->instanceType = $propertyMetadata->args[EmbeddableContainer::class]['class'];
		$this->propertiesMetadata = MetadataStorage::get($this->instanceType)->getProperties();
	}


	public function setPropertyEntity(IEntity $entity): void
	{
		$this->entity = $entity;
	}


	public function convertToRawValue($value)
	{
		return $value;
	}


	public function setRawValue($value): void
	{
		\assert(is_array($value) || $value === null);

		$filtered = \array_filter($value ?: [], function ($val) {
			return $val !== null;
		});

		if (\count($filtered) !== 0) {
			// we do not use constructor to let it optional/configurable by user
			\assert(class_exists($this->instanceType));
			$reflection = new \ReflectionClass($this->instanceType);
			$embeddable = $reflection->newInstanceWithoutConstructor();
			\assert($embeddable instanceof IEmbeddable);
			$embeddable->setRawValue($value ?: []);
		} else {
			$embeddable = null;
		}

		$this->setInjectedValue($embeddable);
	}


	public function getRawValue()
	{
		return $this->value !== null ? $this->value->getRawValue() : null;
	}


	public function setInjectedValue($value): bool
	{
		\assert($this->entity !== null);

		if ($value !== null && !$value instanceof $this->instanceType) {
			throw new InvalidArgumentException("Value has to be instance of {$this->instanceType}" . ($this->metadata->isNullable ? ' or a null.' : '.'));
		} elseif ($value === null && !$this->metadata->isNullable) {
			throw new NullValueException($this->entity, $this->metadata);
		}

		if ($value !== null) {
			\assert($value instanceof IEmbeddable);
			$value->onAttach($this->entity);
		}

		$this->value = $value;
		return true;
	}


	public function hasInjectedValue(): bool
	{
		return $this->value !== null;
	}


	public function &getInjectedValue()
	{
		return $this->value;
	}


	public function __clone()
	{
		if (is_object($this->value)) {
			$this->value = clone $this->value;
		}
	}
}
