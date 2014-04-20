<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Model;

use Nextras\Orm\Relationships\OneHasMany;


/**
 * @property string|NULL $web
 * @property OneHasMany|Book[] $books {1:m BooksRepository}
 * @property OneHasMany|Book[] $translatedBooks {1:m BooksRepository $translator}
 */
final class Author extends Person
{
}
