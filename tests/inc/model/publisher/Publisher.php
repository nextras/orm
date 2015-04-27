<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany as OHM;


/**
 * @property string      $name
 * @property OHM|Book[]  $books  {1:m BooksRepository}
 */
final class Publisher extends Entity
{
}
