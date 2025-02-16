<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\PropertyWrapper;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityAwareProperty;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;


/**
 * @implements IEntityAwareProperty<IEntity>
 */
class PrimaryProxyWrapper implements IPropertyContainer, IEntityAwareProperty
{
	private IEntity $entity;
	private EntityMetadata $metadata;

	public function __construct(
		PropertyMetadata $propertyMetadata, // @phpstan-ignore constructor.unusedParameter
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
}
