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
 * @property int|NULL $id
 */
class Entity extends DataEntityFragment implements IEntity
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


	public function setId($id)
	{
		$key = $this->metadata->getPrimaryKey();
		if (count($key) === 1) {
			$this->setValue('id', $id);
			return;
		}

		if (count($key) !== count($id)) {
			throw new InvalidArgumentException('Insufficient parameters for primary value.');
		}

		foreach ($key as $property) {
			$this->setValue($property, array_shift($id));
		}
	}


	public function getId()
	{
		$key = $this->metadata->getPrimaryKey();
		if (count($key) === 1) {
			return $this->getRawValue($key[0]);
		}

		$primary = [];
		foreach ($key as $property) {
			$primary[] = $this->getRawValue($property);
		}
		return $primary;
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


	public function serialize()
	{
		return serialize(parent::serialize());
	}


	public function unserialize($serialized)
	{
		parent::unserialize(unserialize($serialized));
	}

}
