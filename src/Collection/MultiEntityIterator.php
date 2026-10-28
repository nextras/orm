<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use ArrayObject;
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
	private int $position = 0;

	/** @var list<IEntity> */
	private array $iterable;

	/**
	 * Shared across clones, they all preload from the same data.
	 * @var ArrayObject<string, list<mixed>>
	 */
	private ArrayObject $preloadCache;

	/** @var ArrayObject<string, string> shared across clones, like the preload values */
	private ArrayObject $preloadValuesHashes;


	public function __construct(
		/** @var array<int|string, list<IEntity>> */
		private array $data,
	)
	{
		$this->preloadCache = new ArrayObject();
		$this->preloadValuesHashes = new ArrayObject();
	}


	/**
	 * @param mixed $index
	 */
	public function setDataIndex($index): void
	{
		// no reference into $data, it would separate (copy) the whole array in every clone
		$this->iterable = $this->data[$index] ?? [];
		assert(Arrays::isList($this->iterable));
		$this->rewind();
	}


	public function next(): void
	{
		$this->position++;
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


	public function getPreloadValuesHash(string $property): string
	{
		return $this->preloadValuesHashes[$property] ??=
			hash('xxh3', json_encode($this->getPreloadValues($property), JSON_THROW_ON_ERROR));
	}
}
