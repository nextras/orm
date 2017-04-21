<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;


/**
 * @property int    $id   {primary}
 * @property string $code
 * @property Book   $book {1:1 Book::$ean}
 */
final class Ean extends Entity
{
}
