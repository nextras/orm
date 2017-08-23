<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal\Helpers;

use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\Dbal\StorageReflection\IStorageReflection;


class ColumnReference
{
	/** @var string|array */
	public $column;

	/** @var PropertyMetadata */
	public $propertyMetadata;

	/** @var EntityMetadata */
	public $entityMetadata;

	/** @var IStorageReflection */
	public $storageReflection;


	public function __construct($column, PropertyMetadata $propertyMetadata, EntityMetadata $entityMetadata, IStorageReflection $storageReflection)
	{
		$this->column = $column;
		$this->propertyMetadata = $propertyMetadata;
		$this->entityMetadata = $entityMetadata;
		$this->storageReflection = $storageReflection;
	}
}
