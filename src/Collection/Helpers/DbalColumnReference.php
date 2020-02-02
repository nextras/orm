<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;


class DbalColumnReference
{
	/** @var string|array<string> */
	public $column;

	/** @var PropertyMetadata */
	public $propertyMetadata;

	/** @var EntityMetadata */
	public $entityMetadata;

	/** @var IConventions */
	public $conventions;


	public function __construct($column, PropertyMetadata $propertyMetadata, EntityMetadata $entityMetadata, IConventions $conventions)
	{
		$this->column = $column;
		$this->propertyMetadata = $propertyMetadata;
		$this->entityMetadata = $entityMetadata;
		$this->conventions = $conventions;
	}
}
