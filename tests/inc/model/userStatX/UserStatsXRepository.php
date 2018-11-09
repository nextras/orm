<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


final class UserStatsXRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [UserStatX::class];
	}
}
