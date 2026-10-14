<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper\Dbal;


use function json_encode;
use function md5;


/**
 * Memoizes a hash of preload values used in relationship mappers' cache keys.
 *
 * A relationship mapper is called once per parent entity while iterating a collection, always with the same
 * (cached) list of preload values. Serializing (and hashing) the whole list for every entity made the relationship
 * traversal O(N^2). The memo compares the passed values with the previous ones using identity operator, which
 * is O(1) for the very same array instance and falls back to a full comparison otherwise, so the result is always
 * identical to hashing the values directly.
 *
 * @internal
 */
trait RelationshipCacheKeyTrait
{
	/** @var list<mixed>|null */
	private ?array $lastCacheKeyValues = null;
	private string $lastCacheKeyValuesEncoded = '';


	/**
	 * @param list<mixed> $values
	 */
	private function encodeCacheKeyValues(array $values): string
	{
		if ($this->lastCacheKeyValues !== $values) {
			$this->lastCacheKeyValues = $values;
			$this->lastCacheKeyValuesEncoded = md5((string) json_encode($values));
		}
		return $this->lastCacheKeyValuesEncoded;
	}
}
