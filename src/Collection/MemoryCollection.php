<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


/**
 * This kind of collection promises in-memory processing of data.
 *
 * @template E of \Nextras\Orm\Entity\IEntity
 * @extends ICollection<E>
 */
interface MemoryCollection extends ICollection
{
}

