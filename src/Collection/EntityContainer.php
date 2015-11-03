<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;


class EntityContainer implements IEntityContainer
{
	/** @var array */
	private $data;

	/** @var string */
	private $identification;


	public function __construct(array $data, IEntityPreloadContainer $root = NULL)
	{
		$this->data = $data;
		$this->identification = $root ? $root->getIdentification() : uniqid();
	}


	public function getEntity($key)
	{
		if (!isset($this->data[$key])) {
			return NULL;
		}

		$current = $this->data[$key];
		$current->setPreloadContainer($this);
		return $current;
	}


	public function getPreloadValues($property)
	{
		$values = [];
		foreach ($this->data as $entity) {
			$values[] = $entity->getRawValue($property);
		}
		return $values;
	}


	public function getIdentification()
	{
		return $this->identification;
	}
}
