<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nextras\Orm\Entity\Reflection\PropertyMetadata;


class ValueReference
{
	/** @var mixed */
	public $value;

	/** @var bool */
	public $isMultiValue;

	/** @var PropertyMetadata */
	public $propertyMetadata;


	public function __construct($value, bool $isMultiValue, PropertyMetadata $propertyMetadata)
	{
		$this->value = $value;
		$this->isMultiValue = $isMultiValue;
		$this->propertyMetadata = $propertyMetadata;
	}
}
