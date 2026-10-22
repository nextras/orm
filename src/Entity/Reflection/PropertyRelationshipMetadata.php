<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Repository\IRepository;


class PropertyRelationshipMetadata
{
	public const ONE_HAS_ONE = 1;
	public const ONE_HAS_MANY = 2;
	public const MANY_HAS_ONE = 3;
	public const MANY_HAS_MANY = 4;

	/** @var class-string<IRepository<IEntity>> */
	public string $repository;

	/** @var class-string<IEntity> */
	public string $entity;

	/**
	 * Metadata of the target entity. Resolved lazily through {@see MetadataStorage::get()} on the first access,
	 * so loading an entity's metadata does not load metadata of all related entities.
	 */
	public EntityMetadata $entityMetadata;
	public string|null $property;
	public bool $isMain = false;
	public int $type;

	/** @var array<string, string>|null */
	public ?array $order = null;

	/** @var bool[] */
	public array $cascade = [];


	public function __construct()
	{
		// an unset (not just uninitialized) typed property makes PHP call __get() on read
		unset($this->entityMetadata);
	}


	public function __get(string $name): mixed
	{
		if ($name === 'entityMetadata') {
			return $this->entityMetadata = MetadataStorage::get($this->entity);
		}
		throw new InvalidStateException("Undefined property " . self::class . "::\$$name.");
	}


	public function __isset(string $name): bool
	{
		return $name === 'entityMetadata';
	}


	/**
	 * @return list<string>
	 */
	public function __sleep(): array
	{
		// the target entity metadata are not serialized, they are resolved lazily
		return ['repository', 'entity', 'property', 'isMain', 'type', 'order', 'cascade'];
	}


	public function __wakeup(): void
	{
		unset($this->entityMetadata);
	}
}
