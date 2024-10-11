<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Embeddable;


use Nette\SmartObject;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityAwareProperty;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\NullValueException;
use Nextras\Orm\Model\MetadataStorage;
use ReflectionClass;
use function array_filter;
use function assert;
use function count;


/**
 * @implements IEntityAwareProperty<IEntity>
 */
class EmbeddableContainer implements IPropertyContainer, IEntityAwareProperty
{
	use SmartObject;


	private IEntity|null $entity = null;
	private IEmbeddable|null $value = null;

	/** @var array<string, PropertyMetadata> */
	private array $propertiesMetadata = [];
	private string $instanceType;


	public function __construct(
		private readonly PropertyMetadata $metadata,
	)
	{
		assert($metadata->args !== null);
		$this->instanceType = $metadata->args[EmbeddableContainer::class]['class'];
		$this->propertiesMetadata = MetadataStorage::get($this->instanceType)->getProperties();
	}


	public function onEntityAttach(IEntity $entity): void
	{
		$this->entity = $entity;
	}


	public function onEntityRepositoryAttach(IEntity $entity): void
	{
	}


	public function convertToRawValue($value)
	{
		return $value;
	}


	public function setRawValue($value): void
	{
		assert(is_array($value) || $value === null);

		if (!$this->metadata->isNullable) {
			$hasEmbeddable = true;
		} else {
			$filtered = array_filter($value ?? [], function ($val): bool {
				return $val !== null;
			});
			$hasEmbeddable = count($filtered) !== 0;
		}

		if ($hasEmbeddable) {
			// we do not use constructor to let it optional/configurable by user
			assert(class_exists($this->instanceType));
			$reflection = new ReflectionClass($this->instanceType);
			$embeddable = $reflection->newInstanceWithoutConstructor();
			assert($embeddable instanceof IEmbeddable);
			$embeddable->setRawValue($value ?? []);
		} else {
			$embeddable = null;
		}

		$this->setInjectedValue($embeddable);
	}


	public function getRawValue()
	{
		if ($this->value !== null) {
			return $this->value->getRawValue();
		}

		$out = [];
		foreach ($this->propertiesMetadata as $name => $propertyMetadata) {
			if ($propertyMetadata->isVirtual) continue;
			$out[$name] = null;
		}

		return $out;
	}


	public function setInjectedValue($value): bool
	{
		assert($this->entity !== null);

		if ($value !== null && !$value instanceof $this->instanceType) {
			throw new InvalidArgumentException("Value has to be instance of {$this->instanceType}" . ($this->metadata->isNullable ? ' or a null.' : '.'));
		} elseif ($value === null && !$this->metadata->isNullable) {
			throw new NullValueException($this->metadata);
		}

		if ($value !== null) {
			assert($value instanceof IEmbeddable);
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
