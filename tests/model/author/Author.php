<?php

namespace Nextras\Orm\Tests;

use Nextras\Orm\Relationships\OneHasMany;


/**
 * @property string $web
 * @property OneHasMany|Book[] $books {1:m BooksRepository}
 * @property OneHasMany|Book[] $translatedBooks {1:m BooksRepository $translator}
 */
final class Author extends Person
{
}
