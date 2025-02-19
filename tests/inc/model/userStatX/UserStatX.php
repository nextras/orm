<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Entity\PropertyWrapper\DateWrapper;


/**
 * @property array{User, DateTimeImmutable} $id    {primary-proxy}
 * @property User                           $user  {primary} {m:1 User, oneSided=true}
 * @property DateTimeImmutable              $date  {primary} {wrapper DateWrapper}
 * @property int                            $value
 */
final class UserStatX extends Entity
{
}
