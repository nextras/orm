<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Model\Model as OrmModel;


/**
 * Testing model
 * @property-read AuthorsRepository $authors
 * @property-read BooksRepository $books
 * @property-read BookCollectionsRepository $bookColletions
 * @property-read ContentsRepository $contents
 * @property-read CurrenciesRepository $currencies
 * @property-read EansRepository $eans
 * @property-read LogsRepository $logs
 * @property-read PhotoAlbumsRepository $photoAlbums
 * @property-read PhotosRepository $photos
 * @property-read PublishersRepository $publishers
 * @property-read TagsRepository $tags
 * @property-read TagFollowersRepository $tagFollowers
 * @property-read UsersRepository $users
 * @property-read UserStatsRepository $userStats
 */
class Model extends OrmModel
{
}
