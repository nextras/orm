<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity;

use Nextras\Orm\Entity\Collection\IEntityPreloadContainer;
use Nextras\Orm\Entity\Fragments\DataEntityFragment;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\NotSupportedException;


/**
 * @property mixed $id
 */
class Entity extends DataEntityFragment implements IEntity
{
	/** @var IEntityPreloadContainer */
	private $preloadContainer;


	public function setPreloadContainer(IEntityPreloadContainer $overIterator)
	{
		$this->preloadContainer = $overIterator;
		return $this;
	}


	public function getPreloadContainer()
	{
		return $this->preloadContainer;
	}


	final protected function setId(array $ids)
	{
		$keys = $this->metadata->primaryKey;
		if (count($keys) !== count($ids)) {
			throw new InvalidArgumentException('Insufficient parameters for primary value.');
		}
		foreach ($keys as $key) {
			$this->setValue($key, array_shift($ids));
		}
	}


	final protected function getId()
	{
		$value = [];
		foreach ($this->metadata->primaryKey as $key) {
			$value[] = $this->getValue($key);
		}
		return $value;
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

}
