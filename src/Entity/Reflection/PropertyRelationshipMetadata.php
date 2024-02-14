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
	public $repository;

	/** @var class-string<IEntity> */
	public $entity;

	/** @var EntityMetadata */
	public $entityMetadata;

	/** @var string|null */
	public $property;

	/** @var bool */
	public $isMain = false;

	/** @var int */
	public $type;

	/** @var array<string, string>|null */
	public $order;

	/** @var bool[] */
	public $cascade;
}
