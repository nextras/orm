<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Repository\Repository;


final class LogsRepository extends Repository
{
	static function getEntityClassNames(): array
	{
		return [Log::class];
	}
}
