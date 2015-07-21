<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Collection\IEntityPreloadContainer;
use Nextras\Orm\NotSupportedException;


/**
 * @property int|NULL $id
 */
class Entity extends AbstractEntity implements IEntity
{
	/** @var IEntityPreloadContainer */
	private $preloadContainer;


	public function setPreloadContainer(IEntityPreloadContainer $overIterator = NULL)
	{
		$this->preloadContainer = $overIterator;
		return $this;
	}


	public function getPreloadContainer()
	{
		return $this->preloadContainer;
	}


	public function & __get($name)
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


	public function __clone()
	{
		parent::__clone();
		$this->preloadContainer = NULL;
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
