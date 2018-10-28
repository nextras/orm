<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Collection\IEntityPreloadContainer;
use Nextras\Orm\NotSupportedException;


class Entity extends AbstractEntity implements IEntityHasPreloadContainer
{
	/** @var IEntityPreloadContainer|null */
	private $preloadContainer;


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
		if (!$this->metadata->hasProperty($name)) {
			return false;
		}
		return $this->hasValue($name);
	}


	public function __unset($name)
	{
		throw new NotSupportedException;
	}


	public function __clone()
	{
		parent::__clone();
		$this->preloadContainer = null;
	}


	public function setPreloadContainer(?IEntityPreloadContainer $overIterator)
	{
		$this->preloadContainer = $overIterator;
		return $this;
	}


	public function getPreloadContainer(): ?IEntityPreloadContainer
	{
		return $this->preloadContainer;
	}
}
