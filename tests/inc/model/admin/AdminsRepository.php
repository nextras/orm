<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Admin>
 */
final class AdminsRepository extends Repository
{

	public static function getEntityClassNames(): array
	{
		return [Admin::class];
	}

}
