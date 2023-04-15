<?php declare(strict_types = 1);

namespace Nextras\Orm\Exception;


use Nextras\Orm\Entity\Reflection\PropertyMetadata;


class NullValueException extends InvalidArgumentException
{
	public function __construct(PropertyMetadata $propertyMetadata)
	{
		parent::__construct("Property {$propertyMetadata->containerClassname}::\${$propertyMetadata->name} is not nullable.");
	}
}
