<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Model\Model as OrmModel;


/**
 * Testing model
 * @property-read AuthorsRepository $authors
 * @property-read BooksRepository $books
 * @property-read ContentsRepository $contents
 * @property-read DoughnutsRepository $doughnuts
 * @property-read EansRepository $eans
 * @property-read PublishersRepository $publishers
 * @property-read TagsRepository $tags
 * @property-read TagFollowersRepository $tagFollowers
 */
class Model extends OrmModel
{
}
