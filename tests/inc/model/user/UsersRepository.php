<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method User|NULL getById($id)
 */
final class UsersRepository extends Repository
{
	static function getEntityClassNames()
	{
		return [User::class];
	}
}
