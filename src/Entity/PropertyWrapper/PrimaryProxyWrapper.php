<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\PropertyWrapper;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityAwareProperty;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\PropertyComparator;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Model\MetadataStorage;


/**
 * @implements IEntityAwareProperty<IEntity>
 */
class PrimaryProxyWrapper implements IPropertyContainer, IEntityAwareProperty, PropertyComparator
{
	private IEntity $entity;
	private EntityMetadata $metadata;


	public function __construct(
		private readonly PropertyMetadata $propertyMetadata, // @phpstan-ignore constructor.unusedParameter
	)
	{
	}


	public function onEntityAttach(IEntity $entity): void
	{
		$this->entity = $entity;
		$this->metadata = $entity->getMetadata();
	}


	public function onEntityRepositoryAttach(IEntity $entity): void
	{
	}


	public function setInjectedValue($value): bool
	{
		$this->setRawValue($value);
		return true;
	}


	public function &getInjectedValue()
	{
		$value = $this->getRawValue();
		return $value;
	}


	public function hasInjectedValue(): bool
	{
		$value = $this->getRawValue();
		return isset($value);
	}


	public function convertToRawValue($value)
	{
		return $value;
	}


	public function setRawValue($value): void
	{
		if ($value === null) return;

		$keys = $this->metadata->getPrimaryKey();

		if (count($keys) === 1) {
			$value = [$value];
		} elseif (!is_array($value)) {
			$class = get_class($this->entity);
			throw new InvalidArgumentException("Value for $class::\$id has to be passed as array.");
		}

		if (count($keys) !== count($value)) {
			$class = get_class($this->entity);
			throw new InvalidArgumentException("Value for $class::\$id has insufficient number of parameters.");
		}

		foreach ($keys as $key) {
			$this->entity->setRawValue($key, array_shift($value));
		}
	}


	public function getRawValue()
	{
		if ($this->entity->isPersisted()) {
			return $this->entity->getPersistedId();
		}

		$id = [];
		$keys = $this->metadata->getPrimaryKey();
		foreach ($keys as $key) {
			$id[] = $this->entity->getRawValue($key);
		}
		if (count($keys) === 1) {
			return $id[0];
		} else {
			return $id;
		}
	}


	public function equals(mixed $a, mixed $b): bool
	{
		// equals() is called on a wrapper's prototype during filtering, and therefore
		// it is not connected (yet) to an entity instance;
		$metadata = MetadataStorage::get($this->propertyMetadata->containerClassname);

		foreach ($metadata->getPrimaryKey() as $key) {
			$property = $metadata->getProperty($key);
			$comparator = $property->getPropertyComparator();
			if ($comparator !== null) {
				if (!$comparator->equals($a, $b)) return false;
			} else {
				return $a === $b;
			}
		}
		return true;
	}


	public function compare(mixed $a, mixed $b): int
	{
		// compare() is called on wrapper's prototype during filtering, and therefore
		// it is not connected (yet) to an entity instance;
		$metadata = MetadataStorage::get($this->propertyMetadata->containerClassname);

		$keys = $metadata->getPrimaryKey();
		if (count($keys) !== 1) {
			throw new InvalidArgumentException("The compare() method may be called only for single property proxied primary key.");
		}

		$property = $metadata->getProperty($keys[0]);
		$comparator = $property->getPropertyComparator();
		if ($comparator !== null) {
			return $comparator->compare($a, $b);
		} else {
			return $a <=> $b;
		}
	}
}
