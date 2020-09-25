<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Countable;
use Iterator;
use Nette\Utils\Arrays;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityHasPreloadContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Exception\InvalidStateException;
use function assert;
use function count;
use function spl_object_hash;


/**
 * @implements Iterator<int, IEntity>
 */
class EntityIterator implements IEntityPreloadContainer, Iterator, Countable
{
	/** @var int */
	private $position = 0;

	/**
	 * @var IEntity[]
	 * @phpstan-var list<IEntity>
	 */
	private $iteratable;

	/**
	 * @var array
	 * @phpstan-var array<string, list<mixed>>
	 */
	private $preloadCache;


	/**
	 * @param IEntity[] $data
	 * @phpstan-param list<IEntity> $data
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


	public function getPreloadValues(PropertyMetadata $property): array
	{
		$cacheKey = spl_object_hash($property);
		if (isset($this->preloadCache[$cacheKey])) {
			return $this->preloadCache[$cacheKey];
		}

		$values = [];
		foreach ($this->iteratable as $entity) {
			// $checkPropertyExistence = false - property may not exist when using STI
			$value = $entity->getRawValue($property->path ?? $property->name, false);
			// relationship may be already null-ed in removed entity
			if ($value !== null) {
				$values[] = $value;
			}
		}

		return $this->preloadCache[$cacheKey] = $values;
	}
}
