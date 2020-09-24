<?php declare(strict_types = 1);

namespace Nextras\Orm\Mapper;


use Nextras\Orm\Mapper\Dbal\DbalMapper;


/**
 * @phpstan-template E of \Nextras\Orm\Entity\IEntity
 * @phpstan-extends DbalMapper<E>
 * @deprecated Use {@see DbalMapper} directly.
 */
abstract class Mapper extends DbalMapper
{
}
