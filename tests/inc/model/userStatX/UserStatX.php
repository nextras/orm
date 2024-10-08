<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;


/**
 * @property array             $id    {primary-proxy}
 * @property User              $user  {primary} {m:1 User, oneSided=true}
 * @property DateTimeImmutable $date  {primary}
 * @property int               $value
 */
final class UserStatX extends Entity
{
}
