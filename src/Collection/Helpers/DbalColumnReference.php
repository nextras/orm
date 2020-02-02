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
	/** @var string */
	public $columnPlaceholder;

	/** @var string|array<string> */
	public $column;

	/** @var PropertyMetadata|null */
	public $propertyMetadata;

	/** @var EntityMetadata|null */
	public $entityMetadata;

	/** @var IConventions|null */
	public $conventions;


	public function __construct(
		$columnPlaceholder,
		$column,
		?PropertyMetadata $propertyMetadata,
		?EntityMetadata $entityMetadata,
		?IConventions $conventions
	)
	{
		$this->columnPlaceholder = $columnPlaceholder;
		$this->column = $column;
		$this->propertyMetadata = $propertyMetadata;
		$this->entityMetadata = $entityMetadata;
		$this->conventions = $conventions;
	}
}
