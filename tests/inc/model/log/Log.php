<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;


/**
 * @property \DateTimeImmutable    $id     {primary-proxy}
 * @property \DateTimeImmutable    $date   {primary}
 * @property int $count
 */
final class Log extends Entity
{
}
