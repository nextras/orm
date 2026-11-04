<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<User>
 */
final class UsersRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [User::class];
	}
}
