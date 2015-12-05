<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\NotSupportedException;


class Entity extends AbstractEntity implements IEntity
{
	public function &__get($name)
	{
		$var = $this->getValue($name);
		return $var;
	}


	public function __set($name, $value)
	{
		$this->setValue($name, $value);
	}


	public function __isset($name)
	{
		return $this->hasValue($name);
	}


	public function __unset($name)
	{
		throw new NotSupportedException;
	}


	public function serialize()
	{
		return serialize(parent::serialize());
	}


	public function unserialize($serialized)
	{
		parent::unserialize(unserialize($serialized));
	}
}
