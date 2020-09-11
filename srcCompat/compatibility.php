<?php /** @noinspection PhpMultipleClassesDeclarationsInOneFile */
declare(strict_types = 1);

namespace Nextras\Orm;


use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;


if (false) {
	/** @deprecated use Nextras\Orm\Exception\InvalidArgumentException */
	class InvalidArgumentException extends \InvalidArgumentException
	{
	}
} elseif (!class_exists(InvalidArgumentException::class)) {
	class_alias(\Nextras\Orm\Exception\InvalidArgumentException::class, InvalidArgumentException::class);
}

if (false) {
	/** @deprecated use Nextras\Orm\Entity\Reflection\InvalidModifierDefinitionException */
	class InvalidModifierDefinitionException extends InvalidArgumentException
	{
	}
} elseif (!class_exists(InvalidModifierDefinitionException::class)) {
	class_alias(\Nextras\Orm\Entity\Reflection\InvalidModifierDefinitionException::class, InvalidModifierDefinitionException::class);
}

if (false) {
	/** @deprecated use Nextras\Orm\Exception\NullValueException */
	class NullValueException extends InvalidArgumentException
	{
		public function __construct(IEntity $entity, PropertyMetadata $propertyMetadata)
		{
			$class = get_class($entity);
			parent::__construct("Property {$class}::\${$propertyMetadata->name} is not nullable.");
		}
	}
} elseif (!class_exists(NullValueException::class)) {
	class_alias(\Nextras\Orm\Exception\NullValueException::class, NullValueException::class);
}

if (false) {
	/** @deprecated use Nextras\Orm\Exception\RuntimeException */
	class RuntimeException extends \RuntimeException
	{
	}
} elseif (!class_exists(RuntimeException::class)) {
	class_alias(\Nextras\Orm\Exception\RuntimeException::class, RuntimeException::class);
}

if (false) {
	/** @deprecated use Nextras\Orm\Exception\InvalidStateException */
	class InvalidStateException extends RuntimeException
	{
	}
} elseif (!class_exists(InvalidStateException::class)) {
	class_alias(\Nextras\Orm\Exception\InvalidStateException::class, InvalidStateException::class);
}

if (false) {
	/** @deprecated use Nextras\Orm\Exception\IOException */
	class IOException extends RuntimeException
	{
	}
} elseif (!class_exists(IOException::class)) {
	class_alias(\Nextras\Orm\Exception\IOException::class, IOException::class);
}

if (false) {
	/** @deprecated use Nextras\Orm\Exception\LogicException */
	class LogicException extends \LogicException
	{
	}
} elseif (!class_exists(LogicException::class)) {
	class_alias(\Nextras\Orm\Exception\LogicException::class, LogicException::class);
}

if (false) {
	/** @deprecated use Nextras\Orm\Exception\MemberAccessException */
	class MemberAccessException extends LogicException
	{
	}
} elseif (!class_exists(MemberAccessException::class)) {
	class_alias(\Nextras\Orm\Exception\MemberAccessException::class, MemberAccessException::class);
}

if (false) {
	/** @deprecated use Nextras\Orm\Exception\NotImplementedException */
	class NotImplementedException extends LogicException
	{
	}
} elseif (!class_exists(NotImplementedException::class)) {
	class_alias(\Nextras\Orm\Exception\NotImplementedException::class, NotImplementedException::class);
}

if (false) {
	/** @deprecated use Nextras\Orm\Exception\NotSupportedException */
	class NotSupportedException extends LogicException
	{
	}
} elseif (!class_exists(NotSupportedException::class)) {
	class_alias(\Nextras\Orm\Exception\NotSupportedException::class, NotSupportedException::class);
}
