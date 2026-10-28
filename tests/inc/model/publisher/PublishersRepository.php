<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Publisher>
 */
final class PublishersRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Publisher::class];
	}
}
