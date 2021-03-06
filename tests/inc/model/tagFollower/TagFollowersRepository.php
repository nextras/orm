<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<TagFollower>
 */
final class TagFollowersRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [TagFollower::class];
	}
}
