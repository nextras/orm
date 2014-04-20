<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Model;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany;


/**
 * @property string $title
 * @property Author $author {m:1 AuthorsRepository}
 * @property Author|NULL $translator {m:1 AuthorsRepository}
 * @property ManyHasMany|Tag[] $tags {m:n TagsRepository}
 */
final class Book extends Entity
{
}
