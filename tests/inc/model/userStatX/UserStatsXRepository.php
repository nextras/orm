<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<UserStatX>
 */
final class UserStatsXRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [UserStatX::class];
	}
}
