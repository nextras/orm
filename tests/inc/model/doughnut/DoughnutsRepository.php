<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


/**
 * @method Doughnut|NULL getById($id)
 */
final class DoughnutsRepository extends Repository
{
	static function getEntityClassNames()
	{
		return [Doughnut::class];
	}
}
