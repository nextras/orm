<?php

namespace Nextras\Orm\Tests;

use Nextras\Orm\Model\DIModel;


/**
 * Testing model
 * @property-read AuthorsRepository $authors
 * @property-read BooksRepository $books
 * @property-read TagsRepository $tags
 * @property-read TagFollowersRepository $tagFollowers
 */
class Model extends DIModel
{
}
