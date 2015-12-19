<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany as OHM;


/**
 * @property int         $id     {primary-proxy}
 * @property string      $name
 * @property OHM|Book[]  $books  {1:m Book::$publisher}
 *
 * @property-read int    $publisherId  {primary}
 */
final class Publisher extends Entity
{
}
