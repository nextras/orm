<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


final class UsersRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [User::class];
	}
}
