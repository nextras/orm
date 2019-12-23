<?php declare(strict_types = 1);

namespace Nextras\Orm\Entity\Reflection;


class PropertyRelationshipMetadata
{
	const ONE_HAS_ONE = 1;
	const ONE_HAS_MANY = 2;
	const MANY_HAS_ONE = 3;
	const MANY_HAS_MANY = 4;

	/**
	 * @var string
	 * @phpstan-var class-string<\Nextras\Orm\Repository\IRepository<\Nextras\Orm\Entity\IEntity>>
	 */
	public $repository;

	/**
	 * @var string
	 * @phpstan-var class-string<\Nextras\Orm\Entity\IEntity>
	 */
	public $entity;

	/** @var EntityMetadata */
	public $entityMetadata;

	/** @var string|null */
	public $property;

	/** @var bool */
	public $isMain = false;

	/** @var int */
	public $type;

	/**
	 * @var array|null
	 * @phpstan-var array<string, string>|null
	 */
	public $order;

	/** @var bool[] */
	public $cascade;
}
