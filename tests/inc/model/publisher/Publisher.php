<?php

namespace Nextras\Orm\Tests;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany;


/**
 * @property string $name
 * @property OneHasMany|Book[] $books {1:m BooksRepository}
 */
final class Publisher extends Entity
{
}
