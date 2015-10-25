<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;


/**
 * @property int     $id    {primary}
 * @property string  $code
 * @property Book    $book  {1:1d Book::$ean}
 */
final class Ean extends Entity
{
}
