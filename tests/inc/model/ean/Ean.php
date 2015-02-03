<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;


/**
 * @property string $code
 * @property Book   $book   {1:1d BooksRepository}
 */
final class Ean extends Entity
{
}
