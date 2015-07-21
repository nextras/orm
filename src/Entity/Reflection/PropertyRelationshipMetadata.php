<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Reflection;


class PropertyRelationshipMetadata
{
	const ONE_HAS_ONE = 1;
	const ONE_HAS_ONE_DIRECTED = 2;
	const ONE_HAS_MANY = 3;
	const MANY_HAS_ONE = 4;
	const MANY_HAS_MANY = 5;

	/** @var string */
	public $repository;

	/** @var string */
	public $entity;

	/** @var string */
	public $property;

	/** @var bool */
	public $isMain = FALSE;

	/** @var int */
	public $type;

	/** @var array */
	public $order;
}
