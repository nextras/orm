<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Ean>
 */
final class EansRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Ean::class];
	}
}
