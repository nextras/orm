<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm;


class InvalidArgumentException extends \InvalidArgumentException
{
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
