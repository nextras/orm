<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection;


use Nextras\Orm\Entity\IEntity;


/**
 * This kind of collection promises in-memory processing of data.
 *
 * @template E of IEntity
 * @extends ICollection<E>
 */
interface MemoryCollection extends ICollection
{
}
