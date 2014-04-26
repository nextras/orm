<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Collection;


class EntityContainer implements IEntityContainer
{
	/** @var array */
	private $data;


	public function __construct(array $data)
	{
		$this->data = $data;
	}


	public function getEntity($key)
	{
		$current = $this->data[$key];
		$current->setPreloadContainer($this);
		return $current;
	}


	public function getPreloadPrimaryValues()
	{
		$values = [];
		foreach ($this->data as $entity) {
			$values[] = $entity->id;
		}
		return $values;
	}

}
