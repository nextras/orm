<?php declare(strict_types = 1);

namespace Nextras\Orm\Exception;


use Nextras\Orm\Entity\Reflection\PropertyMetadata;


class InvalidPropertyValueException extends InvalidArgumentException
{
	public function __construct(PropertyMetadata $propertyMetadata)
	{
		parent::__construct("Value for {$propertyMetadata->containerClassname}::\${$propertyMetadata->name} property is invalid.");
	}
}
