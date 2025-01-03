<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Model\Model as OrmModel;


/**
 * Testing model
 * @property-read AuthorsRepository $authors
 * @property-read BooksRepository $books
 * @property-read BookCollectionsRepository $bookCollections
 * @property-read ContentsRepository $contents
 * @property-read EansRepository $eans
 * @property-read LogsRepository $logs
 * @property-read PhotoAlbumsRepository $photoAlbums
 * @property-read PhotosRepository $photos
 * @property-read PublishersRepository $publishers
 * @property-read TagsRepository $tags
 * @property-read TagFollowersRepository $tagFollowers
 * @property-read UsersRepository $users
 * @property-read UserStatsRepository $userStats
 * @property-read UserStatsXRepository $userStatsX
 * @property-read TimeSeriesRepository $timeSeries
 * @property-read AdminsRepository $admins
 * @property-read PersonalDataRepository $personalData
 */
class Model extends OrmModel
{
}
