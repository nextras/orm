<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method TagFollower|NULL getByTagAndAuthor($tagId, $authorId)
 */
final class TagFollowersRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [TagFollower::class];
	}
}
