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


	public function next()
	{
		++$this->position;
	}


	public function current(): ?IEntity
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


	public function key()
	{
		return $this->position;
	}


	public function valid()
	{
		return isset($this->iteratable[$this->position]);
	}


	public function rewind()
	{
		$this->position = 0;
	}


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
