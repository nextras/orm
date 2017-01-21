<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method Photo|NULL getById($id)
 */
final class PhotosRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Photo::class];
	}
}
