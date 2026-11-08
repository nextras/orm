<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Entity\PropertyHooks;


use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use Nextras\Orm\Entity\VirtualEntityProperty;


trait NameHook
{
	#[VirtualEntityProperty]
	public string $displayName {
		get => $this->name;
		set {
			$this->name = $value;
		}
	}
}


/**
 * @property int $id {primary}
 * @property string $name
 * @property DateTimeImmutable|null $born
 * @property-read int $nameLength {virtual}
 */
class HookEntity extends Entity
{
	use NameHook;

	public int $undocumented {
		get => 1;
	}

	#[VirtualEntityProperty]
	public int $age {
		get => $this->born === null ? 0 : 2026 - (int) $this->born->format('Y');
	}

	#[VirtualEntityProperty]
	public DateTimeImmutable|null $birthDate {
		get => $this->born;
	}

	#[VirtualEntityProperty]
	public int|string $identifier {
		get => $this->id;
	}

	/** @var mixed */
	#[VirtualEntityProperty]
	public $untyped {
		get => null;
	}

	#[VirtualEntityProperty]
	public self $myself {
		get => $this;
	}

	public string $backed = 'initial' {
		get => $this->backed;
	}

	#[VirtualEntityProperty]
	public protected(set) string $restricted {
		get => $this->name;
		set {
			$this->name = $value;
		}
	}

	protected int $internal {
		get => 1;
	}


	protected function getterName(string $value): string
	{
		return strtoupper($value);
	}


	protected function getterNameLength(): int
	{
		return strlen($this->name);
	}


	protected function createMetadata(): EntityMetadata
	{
		$dependencies = [];
		$metadata = (new MetadataParser([]))->parseMetadata(static::class, $dependencies);
		// Exercise the same serialized metadata used by the model cache.
		return unserialize(serialize($metadata));
	}
}


class ChildHookEntity extends HookEntity
{
	#[VirtualEntityProperty]
	public int $age {
		get => 42;
	}
}


class InvalidBackedHookEntity extends HookEntity
{
	#[VirtualEntityProperty]
	public string $invalid = 'value';
}


class InvalidProtectedHookEntity extends HookEntity
{
	#[VirtualEntityProperty]
	protected int $invalid {
		get => 1;
	}
}


class InvalidWriteOnlyHookEntity extends HookEntity
{
	#[VirtualEntityProperty]
	public string $invalid {
		set {
			$this->name = $value;
		}
	}
}
