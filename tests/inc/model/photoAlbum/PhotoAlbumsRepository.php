<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<PhotoAlbum>
 */
final class PhotoAlbumsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [PhotoAlbum::class];
	}
}
