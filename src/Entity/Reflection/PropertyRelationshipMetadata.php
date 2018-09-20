<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;


class PropertyRelationshipMetadata
{
	const ONE_HAS_ONE = 1;
	const ONE_HAS_MANY = 2;
	const MANY_HAS_ONE = 3;
	const MANY_HAS_MANY = 4;

	/** @var string */
	public $repository;

	/** @var string */
	public $entity;

	/** @var EntityMetadata */
	public $entityMetadata;

	/** @var string|null */
	public $property;

	/** @var bool */
	public $isMain = false;

	/** @var int */
	public $type;

	/** @var array */
	public $order;

	/** @var bool[] */
	public $cascade;
}
