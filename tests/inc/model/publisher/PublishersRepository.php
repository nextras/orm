<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method Publisher|NULL getById($id)
 */
final class PublishersRepository extends Repository
{
	static function getEntityClassNames()
	{
		return [Publisher::class];
	}
}
