<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;


class InvalidArgumentException extends \InvalidArgumentException
{
}


class InvalidModifierDefinitionException extends InvalidArgumentException
{
}


class NullValueException extends InvalidArgumentException
{
	public function __construct(IEntity $entity, PropertyMetadata $propertyMetadata)
	{
		$class = get_class($entity);
		parent::__construct("Property {$class}::\${$propertyMetadata->name} is not nullable.");
	}
}


class RuntimeException extends \RuntimeException
{
}


class InvalidStateException extends RuntimeException
{
}


class IOException extends RuntimeException
{
}


class LogicException extends \LogicException
{
}


class MemberAccessException extends LogicException
{
}


class NotImplementedException extends LogicException
{
}


class NotSupportedException extends LogicException
{
}
