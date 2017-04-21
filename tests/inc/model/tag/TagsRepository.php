<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method Tag|NULL getById($id)
 * @method Tag|NULL getByName($name)
 */
final class TagsRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Tag::class];
	}
}
