<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


use Nextras\Orm\Entity\IEntity;
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

	public EntityMetadata $entityMetadata;
	public string|null $property;
	public bool $isMain = false;
	public int $type;

	/** @var array<string, string>|null */
	public ?array $order = null;

	/** @var bool[] */
	public array $cascade = [];
}
