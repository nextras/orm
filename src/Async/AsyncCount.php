<?php declare(strict_types = 1);

namespace Nextras\Orm\Async;


use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Exception\InvalidStateException;


/**
 * Requests {@see ICollection::countStored()} of a collection within {@see CollectionFetcher::fetchAll()};
 * read the result with {@see get()} afterwards.
 */
final class AsyncCount
{
	private int|null $count = null;


	public function __construct(
		/** @var ICollection<*> */
		public readonly ICollection $collection,
	)
	{
	}


	public function get(): int
	{
		return $this->count ?? throw new InvalidStateException('Count has not been fetched yet, pass it to CollectionFetcher::fetchAll() first.');
	}


	/** @internal */
	public function set(int $count): void
	{
		$this->count = $count;
	}
}
