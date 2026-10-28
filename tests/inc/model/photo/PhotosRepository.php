<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Photo>
 */
final class PhotosRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Photo::class];
	}
}
