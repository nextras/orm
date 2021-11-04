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
class MultiEntityIterator implements IEntityPreloadContainer, Iterator, Countable
{
	/** @var int */
	private $position = 0;

	/**
	 * @var IEntity[][]
	 * @phpstan-var array<int|string, list<IEntity>>
	 */
	private $data;

	/**
	 * @var IEntity[]
	 * @phpstan-var list<IEntity>
	 */
	private $iterable;

	/**
	 * @var array
	 * @phpstan-var array<string, list<mixed>>
	 */
	private $preloadCache;


	/**
	 * @phpstan-param array<int|string, list<IEntity>> $data
	 */
	public function __construct(array $data)
	{
		$this->data = $data;
	}


	/**
	 * @param mixed $index
	 */
	public function setDataIndex($index): void
	{
		if (!isset($this->data[$index])) {
			$this->data[$index] = [];
		}
		$this->iterable = &$this->data[$index];
		assert(Arrays::isList($this->iterable));
		$this->rewind();
	}


	public function next(): void
	{
		++$this->position;
	}


	public function current(): IEntity
	{
		if (!isset($this->iterable[$this->position])) {
			throw new InvalidStateException();
		}

		$current = $this->iterable[$this->position];
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
		return isset($this->iterable[$this->position]);
	}


	public function rewind(): void
	{
		$this->position = 0;
	}


	public function count(): int
	{
		return count($this->iterable);
	}


	public function getPreloadValues(string $property): array
	{
		if (isset($this->preloadCache[$property])) {
			return $this->preloadCache[$property];
		}

		$values = [];
		foreach ($this->data as $entities) {
			foreach ($entities as $entity) {
				// property may not exist when using STI
				if ($entity->getMetadata()->hasProperty($property)) {
					// relationship may be already nulled in removed entity
					$value = $entity->getRawValue($property);
					if ($value !== null) {
						$values[] = $value;
					}
				}
			}
		}

		return $this->preloadCache[$property] = $values;
	}
}
