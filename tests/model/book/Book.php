<?php

namespace Nextras\Orm\Tests;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany;


/**
 * @property string $title
 * @property Author $author {m:1 AuthorsRepository}
 * @property Author|NULL $translator {m:1 AuthorsRepository}
 * @property ManyHasMany|Tag[] $tags {m:n TagsRepository primary}
 */
final class Book extends Entity
{
}
