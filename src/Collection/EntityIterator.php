<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Countable;
use Iterator;
use Nette\Utils\Arrays;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Exception\InvalidStateException;


/**
 * @implements Iterator<int, IEntity>
 */
class EntityIterator implements IEntityPreloadContainer, Iterator, Countable
{
	private int $position = 0;

	/** @var list<IEntity> */
	private array $iteratable;

	/** @var array<string, list<mixed>> */
	private array $preloadCache = [];


	/**
	 * @param list<IEntity> $data
	 */
	public function __construct(array $data)
	{
		assert(Arrays::isList($data));
		$this->iteratable = $data;
	}


	public function next(): void
	{
		++$this->position;
	}


	public function current(): IEntity
	{
		if (!isset($this->iteratable[$this->position])) {
			throw new InvalidStateException();
		}

		$current = $this->iteratable[$this->position];
		if ($current instanceof IEntityHasPreloadContainer) {
			$current->setPreloadContainer($this);
		}
		return $current;
	}


	#[\ReturnTypeWillChange]
	public function key()
	{
		return $this->position;
	}


	public function valid(): bool
	{
		return isset($this->iteratable[$this->position]);
	}


	public function rewind(): void
	{
		$this->position = 0;
	}


	public function count(): int
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
				// relationship may be already nulled in removed entity
				$value = $entity->getRawValue($property);
				if ($value !== null) {
					$values[] = $value;
				}
			}
		}

		return $this->preloadCache[$property] = $values;
	}
}
