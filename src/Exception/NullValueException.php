<?php declare(strict_types = 1);

namespace Nextras\Orm\Exception;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;


class NullValueException extends InvalidArgumentException
{
	public function __construct(IEntity $entity, PropertyMetadata $propertyMetadata)
	{
		$class = get_class($entity);
		parent::__construct("Property {$class}::\${$propertyMetadata->name} is not nullable.");
	}
}
