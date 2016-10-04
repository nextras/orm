<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method User|NULL getById($id)
 * @method User|NULL getByName($name)
 */
final class UserRepository extends Repository
{
	static function getEntityClassNames()
	{
		return [User::class];
	}
}
