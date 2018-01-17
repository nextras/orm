<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection;

use Countable;
use Iterator;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;


class EntityIterator implements IEntityPreloadContainer, Iterator, Countable
{
	/** @var int */
	private $position = 0;

	/** @var IEntity[] */
	private $iteratable;

	/** @var array */
	private $preloadCache;


	public function __construct(array $data)
	{
		$this->iteratable = $data;
	}


	/**
	 * @return void
	 */
	public function next()
	{
		++$this->position;
	}


	/**
	 * @return IEntity|null
	 */
	public function current()
	{
		if (!isset($this->iteratable[$this->position])) {
			return null;
		}

		$current = $this->iteratable[$this->position];
		if ($current instanceof IEntityHasPreloadContainer) {
			$current->setPreloadContainer($this);
		}
		return $current;
	}


	/**
	 * @return int
	 */
	public function key()
	{
		return $this->position;
	}


	/**
	 * @return bool
	 */
	public function valid()
	{
		return isset($this->iteratable[$this->position]);
	}


	/**
	 * @return void
	 */
	public function rewind()
	{
		$this->position = 0;
	}


	/**
	 * @return int
	 */
	public function count()
	{
		return count($this->iteratable);
	}


	public function getPreloadValues(string $property): array
	{
		if (isset($this->preloadCache[$property])) {
			return $this->preloadCache[$property];
		}

		$values = [];
		foreach ($this->iteratable as $entity) {
			// property may not exist when using STI
			if ($entity->getMetadata()->hasProperty($property)) {
				$values[] = $entity->getRawValue($property);
			}
		}

		return $this->preloadCache[$property] = $values;
	}
}
