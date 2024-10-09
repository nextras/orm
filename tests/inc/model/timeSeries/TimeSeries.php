<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;


/**
 * @property DateTimeImmutable $id     {primary-proxy}
 * @property DateTimeImmutable $date   {primary}
 * @property int               $value
 */
final class TimeSeries extends Entity
{
}
